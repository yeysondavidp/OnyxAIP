<?php

namespace App\Enums;

/**
 * Allow-list of abilities an agent token can carry (ADR-004, US-20.1).
 * Each MCP tool (US-20.2/20.3) requires one of these and then still runs the
 * normal Policy check — an ability never grants more than the policies allow.
 */
enum AgentAbility: string
{
    case RegisterRead     = 'register:read';
    case AssetsWrite      = 'assets:write';
    case JobsWrite        = 'jobs:write';
    case AttachmentsWrite = 'attachments:write';

    public function label(): string
    {
        return match ($this) {
            self::RegisterRead     => 'Read the register',
            self::AssetsWrite      => 'Create and update assets',
            self::JobsWrite        => 'Update service jobs',
            self::AttachmentsWrite => 'Add job attachments',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RegisterRead     => 'Look up clients, stores, assets, display groups and jobs. Never returns passwords or secrets.',
            self::AssetsWrite      => 'Register assets and change their details or status, with the same checks as the asset form.',
            self::JobsWrite        => 'Attach assets to a job and create display groups. Cannot validate, cancel or force-complete a job.',
            self::AttachmentsWrite => 'Upload evidence files to a job, with the same file-type and size limits as the job page.',
        };
    }
}
