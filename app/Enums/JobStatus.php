<?php

namespace App\Enums;

enum JobStatus: string
{
    case Draft               = 'draft';
    case Invited             = 'invited';
    case Accepted            = 'accepted';
    case InProgress          = 'in_progress';
    case Completed           = 'completed';
    case Validated           = 'validated';
    case RequiresRemediation = 'requires_remediation';
    case Cancelled           = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft               => 'Draft',
            self::Invited             => 'Invited',
            self::Accepted            => 'Accepted',
            self::InProgress          => 'In Progress',
            self::Completed           => 'Completed',
            self::Validated           => 'Validated',
            self::RequiresRemediation => 'Requires Remediation',
            self::Cancelled           => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft               => 'neutral',
            self::Invited             => 'info',
            self::Accepted            => 'info',
            self::InProgress          => 'warning',
            self::Completed           => 'positive',
            self::Validated           => 'positive',
            self::RequiresRemediation => 'critical',
            self::Cancelled           => 'neutral',
        };
    }

    /** True when the job is in a terminal state that closes the loop. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Validated, self::Cancelled], strict: true);
    }

    /** True when the job is active and visible on the main board. */
    public function isActive(): bool
    {
        return ! $this->isTerminal() && $this !== self::Cancelled;
    }
}
