<?php

namespace Cinema\Api\Controllers;

use Backend\Classes\Controller;
use Illuminate\Support\Facades\Input;
use Cinema\Api\Classes\Order\TicketModel;
// ...

class TicketController extends Controller
{
    /**
     * print pdf using requested params
     * @return mixed
     */
    public function printPdf() {
        $inputs = $this->getPdfParams();

        $result = (new TicketModel())->pdf(false, $inputs);
        return $result;
    }

    /**
     * send generated pdf via mail to recepient
     * @return array
     */
    public function mailPdf() {
        $inputs = $this->getPdfParams();
        $inputs['rcp'] = Input::get('rcp');

        $result = (new TicketModel($this->locale))->mail($inputs, true);

        return $result;
    }

    /**
     * ...
     */
}