<?php

namespace Cinema\Api\Classes\Order;

use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Cinema\Api\Models\RequestLog as MongoRequestLog;
// ...

/**
 * Class for working with single ticket data (PDF, email)
 */
class TicketModel
{
    const AUDIO_TYPE = array(1);
    const SUBTITLE_TYPE = array(2);

    const PDF_PARAM_PAPER = 'a4';
    const PDF_PARAM_TITLE = 'Ticket.pdf';
    const PDF_PARAM_LOWQUALITY = false;
    const PDF_PARAM_ORIENTATION = 'portrait';

    const EMAIL_TICKETCONFIRMATION_TEMPLATE_ID = 'backend::mail.ticketconfirmation';

    // ...

    /**
     * generate pdf file, just save (for print) or echo out (for email attachment)
     * @return mixed
     */
    public function pdf($saveOnly = false, $inputs = array()) {
        $htmlview       = (bool) $this->getValOrDef($inputs, 'htmlview');
        $templateId     = $this->getValOrDef($inputs, 'ticket_tplid');
        $testTemplate   = $this->getValOrDef($inputs, 'tsttpl') && $templateId;

        $ticketData     = $testTemplate ?
            $this->getTestTicketData() :
            $this->getTicketData(/** .. */);

        $html = '';

        // ...


        $view = View::make('layout', array('content' => $html));
        $html = $view->render();

        if($htmlview) {
            echo $html;
        } else {
            try {
                $pdf = App::make('snappy.pdf.wrapper');

                $pdf
                    ->loadHTML($html)
                    ->setPaper(self::PDF_PARAM_PAPER)
                    ->setOption('title', self::PDF_PARAM_TITLE)
                    ->setOption('lowquality', self::PDF_PARAM_LOWQUALITY)
                    ->setOption('encoding', 'utf-8')
                    ->setOrientation(self::PDF_PARAM_ORIENTATION);

                $filepath = 'app/tickets/ticket' . $transactionId . '.pdf';

                $pdf->generateFromHtml($html, storage_path($filepath), array('encoding' => 'utf-8'), true);

                // ...

                if ($saveOnly) {
                    $result             = $ticket;
                    $result['seats']    = implode(',', $seatNumbers);
                    return array($filepath, $result);
                } else {
                    return $pdf->inline();
                }
            } catch(Exception $e) {
                return '';
            }
        }
    }

    /**
     * send email with attached pdf ticket
     * @param array $inputs
     * @param bool $doAttachPdf
     * @return array
     */
    public function mail($inputs = array(), $doAttachPdf = true) {
        $result     = ['result' => true, 'error' => ''];
        $recepient  = $this->getValOrDef($inputs, 'rcp');

        $params = array();
        $params['CinemaId'] = app('Optimus')->decode($this->getValOrDef($inputs, 'cinema_id'));
        list ($params['BookingId'], $params['BookingNumber']) = explode('-', $this->getValOrDef($inputs, 'vst'));

        list($filepath, $orderData) = $this->pdf(true, $inputs);

        if($recepient) {

            $templateData = $this->getEmailConfirmationTemplateData($orderData);

            // ...

            $path = $filepath ? storage_path($filepath) : '';

            $result = Mail::send(self::EMAIL_TICKETCONFIRMATION_TEMPLATE_ID, $templateData, function ($message) use ($recepient, $path, $templateData, $doAttachPdf) {
                $message->subject($templateData['subject']);
                $message->to($recepient, 'fullname');
                if($doAttachPdf && $path) {
                    $message->attach($path, ['as' => 'Ticket.pdf']);
                }
                $this->logEmail(array('url' => 'email-pdf/send', 'email' => $recepient));
            });
        } else {
            $result['error'] = 'no recepient';
        }

        return $result;
    }

    // get values to fill email confirmation template based on order data (with translated values)
    protected function getEmailConfirmationTemplateData($orderData = array()) {
        $templateData = array();

        $transFilePrefix = 'cinema.ap::lang.mail.';
        $transRows = [
            'thank_you_for_choosing',
            'your_booking_details',
            'cinema',
            'hall',
            'rating',
            'date',
            'runtime',
            'seats',
            'transaction_id',
            'you_can_download',
            'cheers',
            'your_team',
            'booking_id'
        ];
        foreach($transRows as $k=>$v) {
            $data['trans'][$v] = trans($transFilePrefix.$v);
        }

        // glue up seats
        $resultSeats    = array();
        $seats          = explode(',', $orderData['seats']);
        if($seats && is_array($seats)) {
            foreach($seats as $k=>$v) {
                list($row, $seat)   = explode(':', $v);
                $resultSeats[]      = trans($transFilePrefix.'row').' '.$row.'/'.trans($transFilePrefix.'seat').' '.$seat;
            }
        }
        $data['seats']  = implode(', ', $resultSeats);
        $data['from']   = 'Cinema '.$templateData['cinema_city'].' '.$templateData['cinema_name'];

        // ...

        return $templateData;
    }

    protected function logEmail($params = array()) {
        try {
            $log = new MongoRequestLog();
            $log->url = $params['url'];
            $log->email = $params['email'];
            $log->save();
        } catch (Exception $e) {
            Storage::disk('ticket')->put('mongo_failed_'.date('Y.m.d.H.i.s.v').'.log', json_encode($params));
        }

    }
}
