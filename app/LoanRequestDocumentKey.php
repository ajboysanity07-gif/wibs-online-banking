<?php

namespace App;

enum LoanRequestDocumentKey: string
{
    case ApplicationForm = 'application_form';
    case Grepalife = 'grepalife';
    case AffidavitUndertaking = 'affidavit_undertaking';
    case LoanInformation = 'loan_information';
    case PlanOfPayment = 'plan_of_payment';
    case DisclosureStatement = 'disclosure_statement';
    case PromissoryNote = 'promissory_note';
    case UndertakingBarangay = 'undertaking_barangay';
    case LoanSecurityAgreement = 'loan_security_agreement';
    case Generali = 'generali';
    case AuthorityToDeduct = 'authority_to_deduct';
    case DepedSalaryDeductionWaiver = 'deped_salary_deduction_waiver';
    case PensionDeductionWaiver = 'pension_deduction_waiver';
    case GeneraliApplicationForm = 'generali_application_form';

    public function label(): string
    {
        return match ($this) {
            self::ApplicationForm => 'Application Form',
            self::Grepalife => 'GREPALIFE',
            self::AffidavitUndertaking => 'Affidavit of Undertaking',
            self::LoanInformation => 'Loan Information',
            self::PlanOfPayment => 'Plan of Payment',
            self::DisclosureStatement => 'Disclosure Statement',
            self::PromissoryNote => 'Promissory Note',
            self::UndertakingBarangay => 'Undertaking - Barangay',
            self::LoanSecurityAgreement => 'Loan Security Agreement',
            self::Generali => 'Generali (GLAPI) Health Statement',
            self::AuthorityToDeduct => 'Authority to Deduct',
            self::DepedSalaryDeductionWaiver => 'DepEd Salary Deduction Waiver',
            self::PensionDeductionWaiver => 'Pension Deduction Waiver',
            self::GeneraliApplicationForm => 'Generali (GLAPI) Individual Application Form',
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
        return [];
    }
}
