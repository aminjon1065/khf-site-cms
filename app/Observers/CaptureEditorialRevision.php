<?php

namespace App\Observers;

use App\Models\User;
use App\Services\EditorialRevisionService;
use App\Support\EditorialContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CaptureEditorialRevision
{
    public function __construct(
        private readonly EditorialContent $content,
        private readonly EditorialRevisionService $revisions,
    ) {}

    public function saved(Model $model): void
    {
        $user = Auth::user();

        if (! $this->content->supports($model) || ! $user instanceof User) {
            return;
        }

        $this->revisions->capture($model, $user);
    }
}
