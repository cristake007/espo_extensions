<?php

namespace Espo\Modules\ManagementAngajati\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\ManagementAngajati\Instrumente\Prezenta\CalendarPrezentaPersonala;

class ObtineCalendarPrezentaPersonala implements Action
{
    private const string TIP_ENTITATE = 'Prezenta';
    private const string SCOPE_TRADUCERI = 'Prezenta';

    public function __construct(
        private Acl $acl,
        private User $utilizator,
        private CalendarPrezentaPersonala $calendarPrezentaPersonala,
    ) {}

    public function process(Request $cerere): Response
    {
        if (!$this->acl->checkScope(self::TIP_ENTITATE, Table::ACTION_READ)) {
            throw new Forbidden();
        }

        if (!$this->utilizator->isRegular()) {
            throw Forbidden::createWithBody(
                'prezentaDoarPentruAngajati',
                Body::create()->withMessageTranslation(
                    'prezentaDoarPentruAngajati',
                    self::SCOPE_TRADUCERI
                )
            );
        }

        return ResponseComposer::json(
            $this->calendarPrezentaPersonala->obtine(
                $this->utilizator,
                (string) $cerere->getQueryParam('luna')
            )
        );
    }
}
