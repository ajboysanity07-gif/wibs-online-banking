# Graph Report - .  (2026-07-27)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 5036 nodes · 14078 edges · 268 communities (197 shown, 71 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 404 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8b5576b8`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- cn
- api/admin.ts
- Illuminate\Foundation\Http\FormRequest
- types/admin.ts
- Controller
- Illuminate\Http\JsonResponse
- button.tsx
- loan-request-steps.tsx
- AppUser
- staff/loan-request-show.tsx
- LoanRequestAssignmentService
- profile.tsx
- Role
- OrganizationSettingsService
- Illuminate\Database\Eloquent\Factories\Factory
- ApprovedLoanPdfTemplateService
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Illuminate\Support\Collection
- LoanWorkflowProductionSupportService
- LoanRequest
- LoanRequestStatus.php
- Illuminate\Http\Resources\Json\JsonResource
- Illuminate\Database\Schema\Builder
- Illuminate\Http\Request
- AppUser.php
- LoanWorkflowWorkspaceService
- LoanRequestDocumentWorkflowService
- ApprovedLoanDocumentService
- admin-loan-request-correction-dialog.tsx
- notifications.tsx
- LoanRequestDocumentStorage
- PasswordValidationRules.php
- MemberLoanScheduleResource
- MemberApplicationProfile
- ApprovedLoanDocumentService.php
- Wmaster
- LoanRequest.php
- ApprovedLoanDocumentPackageDownloadTest.php
- reports.tsx
- LoanRequestService
- sidebar.tsx
- dependencies
- ApprovedLoanExcelTemplateService
- PromissoryNotePdfService
- MemberLoansRepository
- LoanRequestDecisionService
- pages/dashboard.tsx
- PasswordRecoveryState
- MemberAccountsRepository
- Spa/Superadmin/StaffController.php
- app-header.tsx
- devDependencies
- PasswordRecoveryOtpFactory
- Illuminate\Console\Command
- MemberVerificationMatcher
- LoanRequestPdfService
- RequestsService
- loan-request-records-card.tsx
- Symfony\Component\HttpFoundation\Response
- LoanRequestController
- use-current-url.ts
- MemberLoanExportService
- PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
- app-logo.tsx
- .submit
- components.json
- compilerOptions
- LocationComposer
- MemberLoanService
- PsgcService
- use-appearance.tsx
- request-queue.ts
- LoanRequestWorkflowService
- composer.json
- LoanRequestPerson
- MemberLoanPaymentsRequest
- NotificationResource
- LoanSecurityAgreementPdfService
- 2026_06_15_234636_add_phase_seven_hardening_to_loan_workflow_tables.php
- app-sidebar-layout.tsx
- LoanRequestStoreRequest
- require
- scripts
- optionalDependencies
- scripts
- app-sidebar-header.tsx
- generate-ph-address-data.mjs
- LoanRequestGenerateSignatureLinkRequest
- .mcp.json
- SchemaCapabilities
- app.tsx
- loan-request-step-indicator.tsx
- branding-theme.ts
- branding.ts
- MemberLoanSecurityLedgerResource
- UsernameSuggestionController
- UsernameSuggestionController
- PlanOfPaymentDisclosurePromissoryNoteExcelCellMap
- require-dev
- LoanRequestDraftRequest
- SaveDraftRequest
- MemberDetailResource
- inject-theme.ts
- .grant
- .index
- ProfileUpdateRequest
- setup
- image-crop.ts
- password-recovery-flow.d.ts
- LoanRequestCorrectionService
- PersonName.php
- config
- Branding
- dev:ssr
- use-clipboard.ts
- psr-4
- TestCase
- useIsMobile
- 2026_03_23_020110_add_loan_request_people_foreign_key.php
- 2026_07_06_082651_make_document_type_nullable_on_loan_request_documents_table.php
- UserMenuContent
- MemberVerification
- icon.tsx
- placeholder-pattern.tsx
- loan-payments.blade.php
- loan-request.blade.php
- loan-request-print.blade.php
- class-variance-authority
- clsx
- concurrently
- entrypoint.sh
- @fullcalendar/core
- @fullcalendar/daygrid
- @fullcalendar/react
- globals
- @headlessui/react
- @inertiajs/react
- input-otp
- @radix-ui/react-avatar
- @radix-ui/react-checkbox
- @radix-ui/react-dropdown-menu
- @radix-ui/react-navigation-menu
- @radix-ui/react-slot
- @radix-ui/react-toggle
- @radix-ui/react-toggle-group
- @radix-ui/react-tooltip
- react-dom
- react-easy-crop
- recharts
- sonner
- tailwind-merge
- @tailwindcss/vite
- @tanstack/react-table
- @types/react
- vite
- @vitejs/plugin-react
- loan-request-document.blade.php
- loan-request-styles.blade.php
- admin-requests-table.test.mjs

## God Nodes (most connected - your core abstractions)
1. `AppUser` - 523 edges
2. `LoanRequest` - 444 edges
3. `cn()` - 209 edges
4. `Role` - 171 edges
5. `Controller` - 134 edges
6. `ApprovedLoanDocumentService` - 109 edges
7. `LoanRequestPayloadSerializer` - 79 edges
8. `LoanRequestService` - 77 edges
9. `Wmaster` - 71 edges
10. `LoanRequestAssignmentService` - 69 edges

## Surprising Connections (you probably didn't know these)
- `createRequestsApiAdmin()` --calls--> `AdminProfile`  [INFERRED]
  tests/Feature/Admin/RequestsApiFallbackTest.php → app/Models/AdminProfile.php
- `makeSuperadminForAudit()` --calls--> `AdminProfile`  [INFERRED]
  tests/Feature/AuditLogViewerTest.php → app/Models/AdminProfile.php
- `createLegacyAdminMember()` --calls--> `AdminProfile`  [INFERRED]
  tests/Feature/BackfillLegacyAdminRolesTest.php → app/Models/AdminProfile.php
- `createLegacyAdminStuckUser()` --calls--> `AdminProfile`  [INFERRED]
  tests/Feature/BackfillLegacyAdminRolesTest.php → app/Models/AdminProfile.php
- `createAssignmentActor()` --calls--> `AdminProfile`  [INFERRED]
  tests/Feature/LoanRequestAssignmentWorkflowTest.php → app/Models/AdminProfile.php

## Import Cycles
- None detected.

## Communities (268 total, 71 thin omitted)

### Community 0 - "cn"
Cohesion: 0.03
Nodes (152): LoanRequestPageHero(), LoanRequestPageHeroProps, LoanRequestSearchBox(), LoanRequestSearchBoxProps, LoanRequestStatusFilters(), LoanRequestStatusFiltersProps, LoanRequestSummaryCardItem, LoanRequestSummaryCards() (+144 more)

### Community 1 - "api/admin.ts"
Cohesion: 0.02
Nodes (160): TwoFactorRecoveryCodes(), TwoFactorVerificationStep(), DashboardState, emptySummary, useAdminDashboard(), LoanRequestCancellationOptions, LoanRequestCancellationPayload, useCancelLoanRequest() (+152 more)

### Community 2 - "Illuminate\Foundation\Http\FormRequest"
Cohesion: 0.02
Nodes (42): WibsTrackingController, LoanRequestAdminCorrectedCopyRequest, LoanRequestApproveRequest, LoanRequestCancelRequest, LoanRequestCorrectionReportDismissRequest, LoanRequestDeclineRequest, MemberAccountActionsRequest, MemberAccountLoansRequest (+34 more)

### Community 3 - "types/admin.ts"
Cohesion: 0.03
Nodes (162): MemberDetailPageHeader(), MemberDetailPageHeaderProps, accentStyles, DetailAccent, MemberDetailPrimaryCard(), MemberDetailPrimaryCardProps, MemberDetailSupportingCard(), MemberDetailSupportingCardProps (+154 more)

### Community 4 - "Controller"
Cohesion: 0.03
Nodes (51): MemberAccountsService, AdminDashboardController, MemberLoanPaymentsExportController, MemberLoanScheduleController, MemberLoansController, MemberProfileController, MemberSavingsController, OrganizationSettingsController (+43 more)

### Community 5 - "Illuminate\Http\JsonResponse"
Cohesion: 0.04
Nodes (21): HttpResponse, LoanRequestCorrectionReportController, LoanRequestCorrectionReportController, LoanRequestDecisionController, MembersController, MemberStatusController, RequestsController, LoanRequestWorkflowController (+13 more)

### Community 6 - "button.tsx"
Cohesion: 0.04
Nodes (87): Heading(), InputError(), FormState, ID_TYPE_OPTIONS, LoanRequestPrerequisiteModal(), Props, toFormState(), Props (+79 more)

### Community 7 - "loan-request-steps.tsx"
Cohesion: 0.03
Nodes (119): defaultSlotFieldLabel(), DEPENDENT_ATTRIBUTE_LABELS, DEPENDENT_CATEGORIES, DEPENDENT_SLOT_ATTRIBUTES, DependentCategoryConfig, DependentCategorySection(), DependentCategorySummary, DependentSlotAttribute (+111 more)

### Community 8 - "AppUser"
Cohesion: 0.05
Nodes (21): AdminProfile, AppUser, Permission, MemberAdminAccessService, AdminUserSeeder, Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Foundation\Auth\User, makeNonProcessorAdmin() (+13 more)

### Community 9 - "staff/loan-request-show.tsx"
Cohesion: 0.03
Nodes (101): LoanRequestAuditTrail(), ApprovedDocumentHrefs, buildCoMakerCuratedFields(), buildCoMakerMoreFields(), CancellationProps, CorrectedCopyProps, CorrectionProps, DecisionProps (+93 more)

### Community 10 - "LoanRequestAssignmentService"
Cohesion: 0.06
Nodes (8): LoanRequestChange, StaffManagementService, LoanRequestAssignmentService, LoanRequestNotificationService, LoanRequestProcessingService, LoanRequestStatus, WibsTrackingService, ValidationException

### Community 11 - "profile.tsx"
Cohesion: 0.03
Nodes (95): react, react, CIVIL_STATUS_OPTIONS, EDUCATIONAL_ATTAINMENT_OPTIONS, EMPLOYMENT_TYPE_OPTIONS, fieldError(), FieldLabelProps, fieldName() (+87 more)

### Community 12 - "Role"
Cohesion: 0.04
Nodes (36): CreateNewUser, Role, StaffAccessControl, up(), Illuminate\Validation\ValidationException, Laravel\Fortify\Contracts\CreatesNewUsers, makeSuperadminForAudit(), createLegacyAdminMember() (+28 more)

### Community 13 - "OrganizationSettingsService"
Cohesion: 0.05
Nodes (13): SendLoanDecisionSmsJob, SendLoanWorkflowSmsJob, AbstractDatabaseNotification, LoanRequestWorkflowStatusNotification, OrganizationSettingsService, SemaphoreSmsService, Illuminate\Bus\Queueable, Illuminate\Contracts\Queue\ShouldQueue (+5 more)

### Community 14 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.03
Nodes (28): AdminProfileFactory, static, AppUserFactory, static, DocumentAccessLogFactory, LoanRequestChangeFactory, LoanRequestCorrectionReportFactory, static (+20 more)

### Community 15 - "ApprovedLoanPdfTemplateService"
Cohesion: 0.06
Nodes (10): CalibrateApprovedLoanPdfFieldsCommand, ApprovedLoanImageTemplatePdfService, ApprovedLoanPdfTemplateService, DocumentSignaturePlacement, Command, setasign\Fpdi\Tcpdf\Fpdi, TCPDF, approvedLoanPdfTemplateServiceBoldFieldMap() (+2 more)

### Community 16 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.03
Nodes (19): DocumentAccessLog, LoanRequestCorrectionReport, LoanRequestDataChange, LoanRequestDataEntry, LoanRequestNotificationEvent, LoginHistory, MemberDependentProfile, UserRoleChange (+11 more)

### Community 17 - "Illuminate\Support\Collection"
Cohesion: 0.06
Nodes (20): AbstractReportExport, AuditLogExport, MonthlyApplicationsExport, Carbon, Carbon, ProcessorWorkloadExport, Carbon, RejectionReasonsExport (+12 more)

### Community 18 - "LoanWorkflowProductionSupportService"
Cohesion: 0.07
Nodes (10): LoanWorkflowCleanupTempFilesCommand, LoanWorkflowDeploymentCheckCommand, LoanWorkflowPreflightCommand, LoanWorkflowRepairCommand, LoanWorkflowSendRemindersCommand, LoanWorkflowSmokeTestCommand, parse(), self (+2 more)

### Community 19 - "LoanRequest"
Cohesion: 0.07
Nodes (9): LoanRequest, LoanRequestPolicy, LoanRequestPersonRole, Illuminate\Database\Eloquent\Relations\HasMany, approvedLoanDocumentsCreateApprovedLoanRequestWithPeople(), approvedLoanDocumentsCreateDataEntries(), approvedLoanDocumentsCreateLoanRequestPeopleSnapshots(), approvedLoanDocumentsPersistDataEntry() (+1 more)

### Community 20 - "LoanRequestStatus.php"
Cohesion: 0.04
Nodes (34): memberVisibleValue(), normalized(), normalizeValue(), self, AdminAccessAuditNotification, AdminAccessChangedNotification, LoanRequestAdminCorrectedCreatedNotification, LoanRequestCancelledNotification (+26 more)

### Community 21 - "Illuminate\Http\Resources\Json\JsonResource"
Cohesion: 0.05
Nodes (15): MemberAccountsSummaryResource, MemberLoanResource, MemberLoanSecurityResource, MemberRecentAccountActionResource, MemberLoanPaymentsController, MemberLoanPaymentsController, MemberLoanPaymentsRequest, MemberAccountsSummaryResource (+7 more)

### Community 22 - "Illuminate\Database\Schema\Builder"
Cohesion: 0.08
Nodes (56): addIndexIfMissing(), down(), dropIndexIfExists(), indexExists(), scheduleConnectionName(), schema(), sqlServerIndexExists(), up() (+48 more)

### Community 23 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (9): ReportingController, LoanRequestController, WatchlistController, HandleInertiaRequests, LoanRequestPrerequisiteRequest, WatchlistItemResource, RecordLoginHistory, Illuminate\Http\Request (+1 more)

### Community 24 - "AppUser.php"
Cohesion: 0.07
Nodes (6): SuperadminUserSeeder, Illuminate\Notifications\Notifiable, Laravel\Fortify\TwoFactorAuthenticatable, createApprovedMemberForLoanRequestTests(), createAuditTrailActor(), createAuditTrailChange()

### Community 25 - "LoanWorkflowWorkspaceService"
Cohesion: 0.06
Nodes (13): WorkspaceSwitchController, EnsureAdmin, EnsureLoanWorkflowStaffAccess, EnsureMemberProfileComplete, EnsureMemberVerified, EnsureSuperadmin, EnsureTwoFactorSetup, EnsureUserApproved (+5 more)

### Community 26 - "LoanRequestDocumentWorkflowService"
Cohesion: 0.08
Nodes (7): LoanRequestDocument, LoanRequestDocumentCatalog, LoanRequestDocumentWorkflowService, LoanRequestDocumentKey, applicabilityChecklistEntry(), createGeneratedPdfDocument(), createGeneratedWorkbookDocument()

### Community 27 - "ApprovedLoanDocumentService"
Cohesion: 0.08
Nodes (3): ApprovedLoanDocumentService, LoanRequestPersonRole, Carbon\CarbonInterface

### Community 28 - "admin-loan-request-correction-dialog.tsx"
Cohesion: 0.05
Nodes (53): AdminLoanRequestCorrectionDialog(), applicantChangeFields, applicantRequiredFields, applicantStepFieldKeys, AVAILMENT_OPTIONS, buildInitialFormData(), buildPersonChangeEntries(), ChangeEntry (+45 more)

### Community 29 - "notifications.tsx"
Cohesion: 0.07
Nodes (46): NotificationBell(), NotificationHeader(), ApiResponse, notificationsApi, ACCOUNT_ACCESS_NOTIFICATION_TYPES, buildNotificationMetadataChips(), chipClassNames, conciseDateFormatter (+38 more)

### Community 30 - "LoanRequestDocumentStorage"
Cohesion: 0.07
Nodes (9): AppServiceProvider, FortifyServiceProvider, LoanRequestDocumentStorage, PhAddressLocationProvider, Carbon\CarbonImmutable, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\ServiceProvider, Limit (+1 more)

### Community 31 - "PasswordValidationRules.php"
Cohesion: 0.06
Nodes (16): ResetUserPassword, emailRules(), phoneRules(), profileRules(), usernameRules(), AuthController, PasswordRecoveryPhoneResetController, ResetPasswordWithOtpRequest (+8 more)

### Community 32 - "MemberLoanScheduleResource"
Cohesion: 0.16
Nodes (4): MemberLoanScheduleController, MemberLoanScheduleController, MemberLoanScheduleRequest, MemberLoanScheduleResource

### Community 33 - "MemberApplicationProfile"
Cohesion: 0.05
Nodes (14): MemberApplicationProfile, MemberDependent, UserProfile, DependentsProfileSyncService, createBankingTestMember(), createBeneficiaryTestMember(), createDependentsCycleTestMember(), createDependentsTestMember() (+6 more)

### Community 34 - "ApprovedLoanDocumentService.php"
Cohesion: 0.06
Nodes (11): DisclosureStatementPdfService, AffidavitUndertakingPdfFieldMap, GeneraliPdfFieldMap, GrepalifePdfFieldMap, LoanInformationPdfFieldMap, LoanSecurityAgreementPdfFieldMap, UndertakingBarangayPdfFieldMap, PlanOfPaymentPdfService (+3 more)

### Community 36 - "LoanRequest.php"
Cohesion: 0.05
Nodes (16): OrganizationSetting, OrganizationSettingsUpdatedNotification, LoanRequestPersonFactory, static, DatabaseSeeder, LoanRequestPersonSeeder, LoanRequestSeeder, LoanWorkflowRbacSeeder (+8 more)

### Community 37 - "ApprovedLoanDocumentPackageDownloadTest.php"
Cohesion: 0.08
Nodes (35): TestResponse, approvedLoanDocumentsBuildDocumentData(), approvedLoanDocumentsCreateTemplateImage(), approvedLoanDocumentsCreateTemplatePdf(), approvedLoanDocumentsDecodeCidBytes(), approvedLoanDocumentsDecodePdfStream(), approvedLoanDocumentsDecodePdfTextOperand(), approvedLoanDocumentsDownloadedFilePath() (+27 more)

### Community 38 - "reports.tsx"
Cohesion: 0.12
Nodes (25): AlertError(), Props, Props, MemberProfileDetailItem, MemberProfileDetailsCardProps, BadgeVariant, MemberStatusCardProps, Props (+17 more)

### Community 40 - "sidebar.tsx"
Cohesion: 0.11
Nodes (31): footerNavItems, memberNavItems, PageProps, staffWorkflowNavItems, NavMain(), NavUser(), PageProps, SheetDescription() (+23 more)

### Community 41 - "dependencies"
Cohesion: 0.06
Nodes (35): axios, @fullcalendar/interaction, @fullcalendar/list, laravel-vite-plugin, lucide-react, dependencies, axios, @fullcalendar/interaction (+27 more)

### Community 44 - "MemberLoansRepository"
Cohesion: 0.16
Nodes (5): Amortsched, Wlnled, MemberLoansRepository, Carbon, Illuminate\Support\Carbon

### Community 46 - "pages/dashboard.tsx"
Cohesion: 0.08
Nodes (26): LoanRequestStatusFilterOption, formatCountLabel(), formatDate(), LoanRequestQueuePage(), parseAmount(), adminLoanRequestQueueStatusOptions, buildStaffLoanRequestQueueStatusOptions(), LoanRequestQueueStatusFilter (+18 more)

### Community 47 - "PasswordRecoveryState"
Cohesion: 0.10
Nodes (5): PasswordRecoveryLookupRequest, PasswordRecoveryOtp, PasswordRecoveryService, PasswordRecoveryState, CarbonImmutable

### Community 48 - "MemberAccountsRepository"
Cohesion: 0.17
Nodes (5): MemberAccountsRepository, Wlnmaster, Wsavled, Wsvmaster, MemberAccountsRepository

### Community 49 - "Spa/Superadmin/StaffController.php"
Cohesion: 0.08
Nodes (7): PromoteMemberToStaffRequest, ReactivateStaffAccessRequest, StaffHistoryRequest, StaffIndexRequest, SuspendStaffAccessRequest, UpdateStaffRolesRequest, UserRoleChangeResource

### Community 50 - "app-header.tsx"
Cohesion: 0.10
Nodes (24): AppHeader(), mainNavItems, Props, rightNavItems, NavigationMenu(), NavigationMenuContent(), NavigationMenuIndicator(), NavigationMenuItem() (+16 more)

### Community 51 - "devDependencies"
Cohesion: 0.07
Nodes (29): @aivangogh/ph-address, babel-plugin-react-compiler, eslint, eslint-config-prettier, eslint-import-resolver-typescript, @eslint/js, eslint-plugin-import, eslint-plugin-react (+21 more)

### Community 53 - "Illuminate\Console\Command"
Cohesion: 0.12
Nodes (7): ArchiveOldLoanRequests, BackfillLegacyAdminRoles, BackfillMemberRoles, LoanRequestOwnerRepairCommand, LoanWorkflowSeedPermissionsCommand, LoanWorkflowPermissionSeedService, Illuminate\Console\Command

### Community 54 - "MemberVerificationMatcher"
Cohesion: 0.12
Nodes (5): MemberVerificationController, MemberVerificationController, VerifyMemberRequest, MemberVerificationMatcher, Illuminate\Contracts\Validation\Validator

### Community 55 - "LoanRequestPdfService"
Cohesion: 0.15
Nodes (4): LoanRequestPdfService, LoanRequestPersonRole, OfficialLoanManagerResolver, Illuminate\Contracts\View\View

### Community 57 - "loan-request-records-card.tsx"
Cohesion: 0.13
Nodes (23): LoanRequestMobileCard(), LoanRequestRecordsCard(), LoanRequestRecordsCardProps, requestTableSkeletonColumns, resolveAmount(), resolveLoanTypeLabel(), resolveReference(), resolveTerm() (+15 more)

### Community 60 - "use-current-url.ts"
Cohesion: 0.18
Nodes (19): NavFooter(), IsCurrentUrlFn, IsMatchFn, MatchStrategy, resolvePathname(), UrlMatchOptions, useCurrentUrl(), UseCurrentUrlReturn (+11 more)

### Community 61 - "MemberLoanExportService"
Cohesion: 0.16
Nodes (4): MemberLoanPaymentsExportController, MemberLoanPaymentsExportRequest, MemberLoanExportService, Carbon

### Community 62 - "PhpOffice\PhpSpreadsheet\Worksheet\Worksheet"
Cohesion: 0.22
Nodes (6): PhpOffice\PhpSpreadsheet\Spreadsheet, PhpOffice\PhpSpreadsheet\Worksheet\Drawing, PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, approvedLoanDocumentsDrawingLeftOffsetInPixels(), approvedLoanDocumentsWorkbookStringValues(), approvedLoanDocumentsWorksheetWidthInPixels()

### Community 63 - "app-logo.tsx"
Cohesion: 0.17
Nodes (13): AppLogo(), AppLogoProps, AppLogoIcon(), SupportContact(), SupportContactItem, SupportContactProps, useBranding(), AuthSimpleLayout() (+5 more)

### Community 65 - "components.json"
Cohesion: 0.11
Nodes (18): aliases, components, hooks, lib, ui, utils, iconLibrary, rsc (+10 more)

### Community 66 - "compilerOptions"
Cohesion: 0.11
Nodes (18): resources/js/**/*.d.ts, resources/js/**/*.ts, resources/js/**/*.tsx, compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules (+10 more)

### Community 70 - "use-appearance.tsx"
Cohesion: 0.22
Nodes (16): AppearanceToggleTab(), Appearance, applyTheme(), getStoredAppearance(), handleSystemThemeChange(), initializeTheme(), isDarkMode(), listeners (+8 more)

### Community 71 - "request-queue.ts"
Cohesion: 0.17
Nodes (13): RequestsParams, useRequests(), emptyResponse, RequestQueueParams, useRequestQueue(), RequestsParams, useRequests(), ApiResponse (+5 more)

### Community 73 - "composer.json"
Cohesion: 0.12
Nodes (16): autoload-dev, psr-4, description, extra, laravel, keywords, dont-discover, license (+8 more)

### Community 74 - "LoanRequestPerson"
Cohesion: 0.22
Nodes (4): LoanRequestPerson, LoanRequestCompletenessService, createLoanRequestPeopleSnapshots(), prepareLoanRequestForApproval()

### Community 78 - "2026_06_15_234636_add_phase_seven_hardening_to_loan_workflow_tables.php"
Cohesion: 0.30
Nodes (13): backfillLegacyLoanRequestDocuments(), createRepairAuditTable(), determineWorkflowVersion(), hardenLoanRequestChangesTable(), hardenLoanRequestDataChangesTable(), hardenLoanRequestDataEntriesTable(), hardenLoanRequestDocumentsTable(), hardenLoanRequestNotificationEventsTable() (+5 more)

### Community 79 - "app-sidebar-layout.tsx"
Cohesion: 0.18
Nodes (10): AppContent(), Props, AppShell(), Props, AppSidebar(), AppSidebarHeader(), legacyAdminNavItems(), SidebarInset() (+2 more)

### Community 81 - "require"
Cohesion: 0.14
Nodes (14): require, barryvdh/laravel-dompdf, inertiajs/inertia-laravel, laravel/fortify, laravel/framework, laravel/tinker, laravel/wayfinder, maatwebsite/excel (+6 more)

### Community 82 - "scripts"
Cohesion: 0.11
Nodes (18): scripts, lint, post-autoload-dump, post-create-project-cmd, post-update-cmd, pre-package-uninstall, test, test:lint (+10 more)

### Community 83 - "optionalDependencies"
Cohesion: 0.15
Nodes (13): lightningcss-linux-x64-gnu, lightningcss-win32-x64-msvc, optionalDependencies, lightningcss-linux-x64-gnu, lightningcss-win32-x64-msvc, @rollup/rollup-linux-x64-gnu, @rollup/rollup-win32-x64-msvc, @tailwindcss/oxide-linux-x64-gnu (+5 more)

### Community 84 - "scripts"
Cohesion: 0.15
Nodes (12): private, $schema, scripts, build, build:ssr, dev, format, format:check (+4 more)

### Community 85 - "app-sidebar-header.tsx"
Cohesion: 0.24
Nodes (10): PageProps, Breadcrumbs(), Breadcrumb(), BreadcrumbEllipsis(), BreadcrumbItem(), BreadcrumbLink(), BreadcrumbList(), BreadcrumbPage() (+2 more)

### Community 86 - "generate-ph-address-data.mjs"
Cohesion: 0.22
Nodes (10): buildDataset(), buildLocalities(), DATA_PATH, fixture, FIXTURE_PATH, loadMunicipalities(), normalizeProvinces(), normalizeRegions() (+2 more)

### Community 88 - ".mcp.json"
Cohesion: 0.20
Nodes (11): GITHUB_TOKEN, SQLSERVER_TRUST_CERT, node, npx, context7, github, mssql, playwright (+3 more)

### Community 90 - "app.tsx"
Cohesion: 0.24
Nodes (8): resolveAppTitle(), setup(), SharedProps, ApiNotice(), noticeContent, NoticeType, Toaster(), ToasterProps

### Community 91 - "loan-request-step-indicator.tsx"
Cohesion: 0.22
Nodes (8): GROUP_META, LoanRequestStepIndicator(), Props, STEP_GROUPS, StepGroup, LoanRequestWizardGroupId, LoanRequestWizardStep, loanRequestWizardSteps

### Community 92 - "branding-theme.ts"
Cohesion: 0.33
Nodes (10): formatHsl(), hexToRgb(), HslColor, normalizeHexColor(), relativeLuminance(), resolveBrandingTheme(), resolveForegroundColor(), rgbChannelToLinear() (+2 more)

### Community 93 - "branding.ts"
Cohesion: 0.18
Nodes (10): BrandingAssets, BrandingCommunications, BrandingContact, BrandingGeneral, BrandingReports, LoanSmsTemplates, LogoPreset, ReportHeader (+2 more)

### Community 94 - "MemberLoanSecurityLedgerResource"
Cohesion: 0.29
Nodes (3): MemberLoanSecurityLedgerResource, MemberSavingsController, MemberSavingsLedgerResource

### Community 98 - "require-dev"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+2 more)

### Community 102 - "inject-theme.ts"
Cohesion: 0.33
Nodes (6): mrdincTheme, injectClientTheme(), serializeTokens(), ClientTheme, ThemeMode, ThemeTokens

### Community 106 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 107 - "image-crop.ts"
Cohesion: 0.36
Nodes (7): createCroppedImageFile(), CreateCroppedImageOptions, CroppedImageResult, getOutputFileName(), getOutputMimeType(), loadImage(), MIME_EXTENSION

### Community 108 - "password-recovery-flow.d.ts"
Cohesion: 0.25
Nodes (7): PasswordRecoveryProgressItem, PasswordRecoveryProgressState, PasswordRecoveryProgressStepId, PasswordRecoveryStateStep, PasswordRecoveryStepContent, PasswordRecoveryTransitionDirection, PasswordRecoveryWizardStep

### Community 112 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 113 - "Branding"
Cohesion: 0.33
Nodes (4): SharedProps, Branding, InertiaConfig, @inertiajs/core

### Community 114 - "dev:ssr"
Cohesion: 0.33
Nodes (6): dev, dev:ssr, Composer\\Config::disableProcessTimeout, npm run build:ssr, npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"php artisan pail --timeout=0\" \"php artisan inertia:start-ssr\" --names=server,queue,logs,ssr --kill-others, npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite

### Community 115 - "use-clipboard.ts"
Cohesion: 0.33
Nodes (5): TwoFactorSetupStep(), CopiedValue, CopyFn, useClipboard(), UseClipboardReturn

### Community 116 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 118 - "useIsMobile"
Cohesion: 0.70
Nodes (4): getServerSnapshot(), isSmallerThanBreakpoint(), mediaQueryListener(), useIsMobile()

### Community 120 - "2026_03_23_020110_add_loan_request_people_foreign_key.php"
Cohesion: 0.83
Nodes (3): down(), foreignKeyExists(), up()

### Community 122 - "UserMenuContent"
Cohesion: 0.50
Nodes (3): UserMenuContent(), CleanupFn, useMobileNavigation()

## Knowledge Gaps
- **748 isolated node(s):** `@playwright/mcp`, `@upstash/context7-mcp`, `node`, `SQLSERVER_TRUST_CERT`, `@modelcontextprotocol/server-github` (+743 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **71 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `LoanRequestDocumentKey` connect `LoanRequestDocumentWorkflowService` to `loan-request-steps.tsx`, `staff/loan-request-show.tsx`, `LoanRequestPerson`, `Symfony\Component\HttpFoundation\Response`, `ApprovedLoanDocumentService`?**
  _High betweenness centrality (0.306) - this node is a cross-community bridge._
- **Why does `LoanRequest` connect `LoanRequest` to `Illuminate\Foundation\Http\FormRequest`, `Controller`, `Illuminate\Http\JsonResponse`, `AppUser`, `LoanRequestAssignmentService`, `Role`, `OrganizationSettingsService`, `Illuminate\Database\Eloquent\Factories\Factory`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Illuminate\Support\Collection`, `LoanWorkflowProductionSupportService`, `LoanRequestStatus.php`, `Illuminate\Http\Request`, `AppUser.php`, `LoanRequestDocumentWorkflowService`, `ApprovedLoanDocumentService`, `MemberApplicationProfile`, `ApprovedLoanDocumentService.php`, `LoanRequest.php`, `ApprovedLoanDocumentPackageDownloadTest.php`, `LoanRequestService`, `LoanRequestDecisionService`, `PasswordRecoveryState`, `Illuminate\Console\Command`, `LoanRequestPdfService`, `RequestsService`, `Symfony\Component\HttpFoundation\Response`, `LoanRequestController`, `.submit`, `LoanRequestWorkflowService`, `LoanRequestPerson`, `.index`, `LoanRequestCorrectionService`?**
  _High betweenness centrality (0.233) - this node is a cross-community bridge._
- **Why does `AppUser` connect `AppUser` to `Illuminate\Foundation\Http\FormRequest`, `Controller`, `Illuminate\Http\JsonResponse`, `LoanRequestAssignmentService`, `Role`, `OrganizationSettingsService`, `Illuminate\Database\Eloquent\Factories\Factory`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `Illuminate\Support\Collection`, `LoanWorkflowProductionSupportService`, `LoanRequest`, `LoanRequestStatus.php`, `Illuminate\Http\Resources\Json\JsonResource`, `Illuminate\Database\Schema\Builder`, `Illuminate\Http\Request`, `AppUser.php`, `LoanWorkflowWorkspaceService`, `LoanRequestDocumentWorkflowService`, `LoanRequestDocumentStorage`, `PasswordValidationRules.php`, `MemberApplicationProfile`, `Wmaster`, `LoanRequest.php`, `LoanRequestService`, `MemberLoansRepository`, `LoanRequestDecisionService`, `PasswordRecoveryState`, `Spa/Superadmin/StaffController.php`, `PasswordRecoveryOtpFactory`, `Illuminate\Console\Command`, `LoanRequestPdfService`, `RequestsService`, `LoanRequestController`, `MemberLoanExportService`, `.submit`, `MemberLoanService`, `LoanRequestWorkflowService`, `LoanRequestPerson`, `UsernameSuggestionController`, `UsernameSuggestionController`, `.grant`, `LoanRequestCorrectionService`?**
  _High betweenness centrality (0.183) - this node is a cross-community bridge._
- **Are the 26 inferred relationships involving `AppUser` (e.g. with `.handle()` and `.handle()`) actually correct?**
  _`AppUser` has 26 INFERRED edges - model-reasoned connections that need verification._
- **Are the 23 inferred relationships involving `LoanRequest` (e.g. with `.handle()` and `.handle()`) actually correct?**
  _`LoanRequest` has 23 INFERRED edges - model-reasoned connections that need verification._
- **What connects `@playwright/mcp`, `@upstash/context7-mcp`, `node` to the rest of the system?**
  _748 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `cn` be split into smaller, more focused modules?**
  _Cohesion score 0.02843675541282503 - nodes in this community are weakly interconnected._