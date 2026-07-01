<?php

namespace App\Enums;

/**
 * Every system email template slot (US-16.2, SRA §12.1/§12.2) — one case per
 * PM/technician notification type. This is the single source of truth for the
 * allow-listed variables per slot; both the admin UI sidebar and the
 * EmailTemplateRenderer's server-side validation read it from here.
 *
 * Only JobInvitation has a live sender today (EPIC-09's JobInvitationService).
 * The remaining eight slots belong to EPIC-13 (Notifications, not yet built) —
 * they exist here now so a PM can review/customise them ahead of time; each
 * shows "Using default" in the admin UI until EPIC-13 ships and a PM edits it.
 */
enum EmailTemplateSlot: string
{
    case JobInvitation               = 'tech_job_invitation';
    case JobReminder                 = 'tech_job_reminder';
    case LinkExpiryWarning           = 'tech_link_expiry_warning';
    case PmJobStatusChanged          = 'pm_job_status_changed';
    case PmAssetStatusChanged        = 'pm_asset_status_changed';
    case PmNewFaultReported          = 'pm_new_fault_reported';
    case PmSlaWarning                = 'pm_sla_warning';
    case PmSlaBreached               = 'pm_sla_breached';
    case PmWarrantyExpiryApproaching = 'pm_warranty_expiry_approaching';

    public function label(): string
    {
        return $this->definition()['label'];
    }

    /** @return array<string, string> variable key => description, shown in the UI sidebar */
    public function allowedVariables(): array
    {
        return $this->definition()['variables'];
    }

    /** @return list<string> */
    public function requiredVariables(): array
    {
        return $this->definition()['required'];
    }

    public function defaultSubject(): string
    {
        return $this->definition()['subject'];
    }

    public function defaultBody(): string
    {
        return $this->definition()['body'];
    }

    /** @return array<string, string> representative values for the read-only Preview panel */
    public function dummyVariables(): array
    {
        return $this->definition()['dummy'];
    }

    /**
     * @return array{
     *     label: string,
     *     variables: array<string, string>,
     *     required: list<string>,
     *     subject: string,
     *     body: string,
     *     dummy: array<string, string>,
     * }
     */
    private function definition(): array
    {
        return match ($this) {
            self::JobInvitation => [
                'label'     => 'Technician — job invitation',
                'variables' => [
                    'technician_name' => "The technician's name",
                    'job_reference'   => 'Job reference code',
                    'job_name'        => 'Job title',
                    'store_name'      => 'Store name',
                    'store_address'   => 'Store street address',
                    'scheduled_date'  => 'Scheduled date and time, in the store timezone',
                    'signed_url'      => 'The signed link to open the job',
                ],
                'required' => ['signed_url'],
                'subject'  => 'Job invitation: {{job_name}}',
                'body'     => "Hi {{technician_name}}, you've been invited to attend the following service visit.\n\n"
                    ."{{job_name}} ({{job_reference}})\n{{store_name}}, {{store_address}}\n{{scheduled_date}}\n\n"
                    .'Open the job here: {{signed_url}}',
                'dummy' => [
                    'technician_name' => 'Michael Chen',
                    'job_reference'   => 'JOB-0042',
                    'job_name'        => 'Quarterly Screen Maintenance',
                    'store_name'      => 'Pandora Pitt St Mall',
                    'store_address'   => '188 Pitt St, Sydney NSW',
                    'scheduled_date'  => 'Wednesday 1 July 2026 at 9:00 am AEST',
                    'signed_url'      => 'https://aip.onyxvisual.com.au/t/job/abc123',
                ],
            ],

            self::JobReminder => [
                'label'     => 'Technician — job reminder',
                'variables' => [
                    'technician_name' => "The technician's name",
                    'job_reference'   => 'Job reference code',
                    'job_name'        => 'Job title',
                    'store_name'      => 'Store name',
                    'store_address'   => 'Store street address',
                    'scheduled_date'  => 'Scheduled date and time, in the store timezone',
                    'signed_url'      => 'The signed link to open the job',
                ],
                'required' => ['signed_url'],
                'subject'  => 'Reminder: {{job_name}} tomorrow',
                'body'     => "Hi {{technician_name}}, this is a reminder about your upcoming visit.\n\n"
                    ."{{job_name}} ({{job_reference}})\n{{store_name}}, {{store_address}}\n{{scheduled_date}}\n\n"
                    .'Open the job here: {{signed_url}}',
                'dummy' => [
                    'technician_name' => 'Michael Chen',
                    'job_reference'   => 'JOB-0042',
                    'job_name'        => 'Quarterly Screen Maintenance',
                    'store_name'      => 'Pandora Pitt St Mall',
                    'store_address'   => '188 Pitt St, Sydney NSW',
                    'scheduled_date'  => 'Wednesday 1 July 2026 at 9:00 am AEST',
                    'signed_url'      => 'https://aip.onyxvisual.com.au/t/job/abc123',
                ],
            ],

            self::LinkExpiryWarning => [
                'label'     => 'Technician — link expiry warning',
                'variables' => [
                    'technician_name' => "The technician's name",
                    'job_reference'   => 'Job reference code',
                    'job_name'        => 'Job title',
                    'signed_url'      => 'A freshly re-issued signed link',
                ],
                'required' => ['signed_url'],
                'subject'  => 'Your job link was renewed: {{job_name}}',
                'body'     => "Hi {{technician_name}}, your job link was renewed — use this new link to access your job.\n\n"
                    ."{{job_name}} ({{job_reference}})\n\n"
                    .'{{signed_url}}',
                'dummy' => [
                    'technician_name' => 'Michael Chen',
                    'job_reference'   => 'JOB-0042',
                    'job_name'        => 'Quarterly Screen Maintenance',
                    'signed_url'      => 'https://aip.onyxvisual.com.au/t/job/def456',
                ],
            ],

            self::PmJobStatusChanged => [
                'label'     => 'PM — job status changed',
                'variables' => [
                    'job_reference' => 'Job reference code',
                    'job_name'      => 'Job title',
                    'store_name'    => 'Store name',
                    'new_status'    => 'The job\'s new status',
                    'job_url'       => 'Link to the job detail page',
                ],
                'required' => ['job_url'],
                'subject'  => 'Job {{job_reference}} is now {{new_status}}',
                'body'     => "{{job_name}} ({{job_reference}}) at {{store_name}} is now {{new_status}}.\n\n"
                    .'View the job: {{job_url}}',
                'dummy' => [
                    'job_reference' => 'JOB-0042',
                    'job_name'      => 'Quarterly Screen Maintenance',
                    'store_name'    => 'Pandora Pitt St Mall',
                    'new_status'    => 'Completed',
                    'job_url'       => 'https://aip.onyxvisual.com.au/jobs/42',
                ],
            ],

            self::PmAssetStatusChanged => [
                'label'     => 'PM — asset status changed',
                'variables' => [
                    'asset_code' => 'Asset code',
                    'asset_name' => 'Asset name',
                    'store_name' => 'Store name',
                    'old_status' => "The asset's previous status",
                    'new_status' => "The asset's new status",
                    'asset_url'  => 'Link to the asset detail page',
                ],
                'required' => ['asset_url'],
                'subject'  => 'Asset {{asset_code}} changed to {{new_status}}',
                'body'     => "{{asset_name}} ({{asset_code}}) at {{store_name}} changed from {{old_status}} to {{new_status}}.\n\n"
                    .'View the asset: {{asset_url}}',
                'dummy' => [
                    'asset_code' => 'PAN-SCR-001',
                    'asset_name' => 'Samsung QH98C',
                    'store_name' => 'Pandora Pitt St Mall',
                    'old_status' => 'Active',
                    'new_status' => 'Faulty',
                    'asset_url'  => 'https://aip.onyxvisual.com.au/assets/1',
                ],
            ],

            self::PmNewFaultReported => [
                'label'     => 'PM — new fault reported',
                'variables' => [
                    'asset_code'    => 'Asset code',
                    'asset_name'    => 'Asset name',
                    'store_name'    => 'Store name',
                    'job_reference' => 'Job reference the fault was reported against',
                    'asset_url'     => 'Link to the asset detail page',
                ],
                'required' => ['asset_url'],
                'subject'  => 'New fault reported: {{asset_code}}',
                'body'     => 'A technician reported a fault on {{asset_name}} ({{asset_code}}) at {{store_name}} '
                    ."during job {{job_reference}}.\n\n"
                    .'View the asset: {{asset_url}}',
                'dummy' => [
                    'asset_code'    => 'PAN-SCR-001',
                    'asset_name'    => 'Samsung QH98C',
                    'store_name'    => 'Pandora Pitt St Mall',
                    'job_reference' => 'JOB-0042',
                    'asset_url'     => 'https://aip.onyxvisual.com.au/assets/1',
                ],
            ],

            self::PmSlaWarning => [
                'label'     => 'PM — SLA at-risk warning',
                'variables' => [
                    'job_reference'   => 'Job reference code',
                    'store_name'      => 'Store name',
                    'client_name'     => 'Client name',
                    'percent_elapsed' => 'Percentage of the SLA window elapsed',
                    'job_url'         => 'Link to the job detail page',
                ],
                'required' => ['job_url'],
                'subject'  => 'SLA at risk: {{job_reference}}',
                'body'     => 'Your SLA for {{job_reference}} at {{store_name}} ({{client_name}}) is '
                    ."{{percent_elapsed}}% elapsed.\n\n"
                    .'View the job: {{job_url}}',
                'dummy' => [
                    'job_reference'   => 'JOB-0099',
                    'store_name'      => 'Sephora Bondi Junction',
                    'client_name'     => 'Sephora Australia',
                    'percent_elapsed' => '80',
                    'job_url'         => 'https://aip.onyxvisual.com.au/jobs/99',
                ],
            ],

            self::PmSlaBreached => [
                'label'     => 'PM — SLA breached',
                'variables' => [
                    'job_reference' => 'Job reference code',
                    'store_name'    => 'Store name',
                    'client_name'   => 'Client name',
                    'job_url'       => 'Link to the job detail page',
                ],
                'required' => ['job_url'],
                'subject'  => 'SLA breached: {{job_reference}}',
                'body'     => "The SLA for {{job_reference}} at {{store_name}} ({{client_name}}) has been breached.\n\n"
                    .'View the job: {{job_url}}',
                'dummy' => [
                    'job_reference' => 'JOB-0099',
                    'store_name'    => 'Sephora Bondi Junction',
                    'client_name'   => 'Sephora Australia',
                    'job_url'       => 'https://aip.onyxvisual.com.au/jobs/99',
                ],
            ],

            self::PmWarrantyExpiryApproaching => [
                'label'     => 'PM — warranty expiry approaching',
                'variables' => [
                    'asset_code'     => 'Asset code',
                    'asset_name'     => 'Asset name (manufacturer + model)',
                    'store_name'     => 'Store name',
                    'expiry_date'    => 'Warranty expiry date (DD/MM/YYYY)',
                    'days_remaining' => 'Days remaining until expiry',
                    'asset_url'      => 'Link to the asset detail page',
                ],
                'required' => ['asset_url'],
                'subject'  => 'Warranty expiring soon: {{asset_code}}',
                'body'     => 'The warranty for {{asset_name}} ({{asset_code}}) at {{store_name}} expires in '
                    ."{{days_remaining}} days ({{expiry_date}}). Plan ahead to avoid coverage gaps.\n\n"
                    .'View the asset: {{asset_url}}',
                'dummy' => [
                    'asset_code'     => 'PAN-SCR-001',
                    'asset_name'     => 'Samsung QH98C',
                    'store_name'     => 'Pandora Pitt St Mall',
                    'expiry_date'    => '15/07/2026',
                    'days_remaining' => '30',
                    'asset_url'      => 'https://aip.onyxvisual.com.au/assets/1',
                ],
            ],
        };
    }
}
