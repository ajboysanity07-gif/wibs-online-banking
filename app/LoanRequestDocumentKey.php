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
            self::AffidavitUndertaking => 'Affidavit of Undertaking (ATM Payout)',
            self::Authorization => 'Authorization',
            self::LoanInformation => 'Loan Information',
            self::PlanOfPayment => 'Plan of Payment',
            self::DisclosureStatement => 'Disclosure Statement',
            self::PromissoryNote => 'Promissory Note',
            self::UndertakingBarangay => 'Undertaking (BLGU)',
            self::LoanSecurityAgreement => 'Loan Security Agreement',
            self::Generali => 'Generali (GLAPI) Health Statement',
            self::AuthorityToDeduct => 'Authority to Deduct (Salary Deduction)',
            self::DepedSalaryDeductionWaiver => 'Salary Deduction Authorization Waiver (Education Sector)',
            self::PensionDeductionWaiver => 'Waiver (Pensioners)',
            self::GeneraliApplicationForm => 'Generali (GLAPI) Individual Application Form',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ApplicationForm,
            self::LoanInformation,
            self::PlanOfPayment,
            self::DisclosureStatement,
            self::PromissoryNote,
            self::LoanSecurityAgreement => 'loan_paperwork',
            self::Grepalife,
            self::Generali,
            self::GeneraliApplicationForm => 'insurance',
            self::AffidavitUndertaking,
            self::Authorization,
            self::UndertakingBarangay,
            self::AuthorityToDeduct,
            self::DepedSalaryDeductionWaiver,
            self::PensionDeductionWaiver => 'repayment_authorization',
        };
    }

    public function groupLabel(): string
    {
        return match ($this->group()) {
            'loan_paperwork' => 'Loan Paperwork',
            'insurance' => 'Insurance',
            'repayment_authorization' => 'Repayment Authorization',
            default => 'Other',
        };
    }

    /**
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return ['loan_paperwork', 'insurance', 'repayment_authorization'];
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
