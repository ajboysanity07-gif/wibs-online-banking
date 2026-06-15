<?php

namespace App;

enum LoanRequestDocumentKey: string
{
    case ApplicationForm = 'application_form';
    case Grepalife = 'grepalife';
    case AffidavitUndertaking = 'affidavit_undertaking';
    case Authorization = 'authorization';
    case LoanInformation = 'loan_information';
    case PlanOfPayment = 'plan_of_payment';
    case DisclosureStatement = 'disclosure_statement';
    case PromissoryNote = 'promissory_note';
    case UndertakingBarangay = 'undertaking_barangay';
    case LoanSecurityAgreement = 'loan_security_agreement';

    public function label(): string
    {
        return match ($this) {
            self::ApplicationForm => 'Application Form',
            self::Grepalife => 'GREPALIFE',
            self::AffidavitUndertaking => 'Affidavit of Undertaking',
            self::Authorization => 'Authorization',
            self::LoanInformation => 'Loan Information',
            self::PlanOfPayment => 'Plan of Payment',
            self::DisclosureStatement => 'Disclosure Statement',
            self::PromissoryNote => 'Promissory Note',
            self::UndertakingBarangay => 'Undertaking - Barangay',
            self::LoanSecurityAgreement => 'Loan Security Agreement',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $document): string => $document->value,
            self::cases(),
        );
    }

    /**
     * @return list<self>
     */
    public static function workbookDocuments(): array
    {
        return [
            self::LoanInformation,
            self::PlanOfPayment,
            self::DisclosureStatement,
            self::PromissoryNote,
        ];
    }
}
