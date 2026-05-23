<?php

namespace App\Enums;

enum KnowledgeSourceStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isInProgress(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }

    public function canReprocess(): bool
    {
        return in_array($this, [self::Ready, self::Failed, self::Cancelled], true);
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Ready, self::Failed], true);
    }

    public function canCancel(): bool
    {
        return $this->isInProgress();
    }

    public static function tryFromStored(?string $status): ?self
    {
        if ($status === 'draft') {
            return self::Queued;
        }

        return self::tryFrom((string) $status);
    }

    public static function fromStored(string $status): self
    {
        return self::tryFromStored($status) ?? self::Queued;
    }
}
