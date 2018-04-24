<?php

namespace Cinema\Api\Classes\SeatPlan;

use Log;
use Input;
use Exception;
use Cinema\Api\Classes\RawTransformer;

/**
 * Class for parsing & transforming foreign types into frontend-friendly version
 */
class TicketTypesTransformer
{
    const SPECIAL_AREA_CODE = 115;
    protected $hiddenTypesPool = ['startWith' => 500, 'endWith' => 900];

    protected function getFilteredTickets()
    {
        $ticketTypes = collect($this->ticketTypes['Tickets'])->groupBy('AreaCategoryCode');
        $locale = Input::get('locale');

        foreach ($ticketTypes as $areaCode => $areaTicketTypes) {
            $this->response[$areaCode] = $areaTicketTypes->filter(function ($type) use($areaCode) {
                return $type['AreaCategoryCode'] === $areaCode
                    && $this->filterLoyality($type)
                    && $type['IsComplimentaryTicket'] === false
                    && $type['IsMagicallyPriced'] === false
                    && ((int)$type['HeadOfficeGroupingCode'] < $this->hiddenTypesPool['startWith'] || (int)$type['HeadOfficeGroupingCode'] > $this->hiddenTypesPool['endWith'])
                    ;
            })->sortBy(function ($type, $key) {
                return $type['DisplaySequence'];
            })->map(function ($type) use($locale) {

                $languageTag = collect(RawTransformer::LOCALES)->flip();

                foreach ($type['LongDescriptionTranslations'] as $key => $translation) {
                    if (isset($translation['LanguageTag']) && $translation['LanguageTag'] == $languageTag[$locale]) {
                        $type['LongDescription'] = $translation['Text'];
                        break;
                    }
                }

                return collect($type)
                    ->only([
                        'Description',
                        'LongDescription',
                        'TicketTypeCode',
                        'PriceInCents',
                        'MagicNumberId'
                    ]);
            })->values()->toArray();
        }
    }

    // ...
}
