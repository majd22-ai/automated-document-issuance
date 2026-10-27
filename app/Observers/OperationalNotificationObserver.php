<?php

namespace App\Observers;

use App\Services\Notifications\OperationalNotifications;
use Illuminate\Database\Eloquent\Model;

class OperationalNotificationObserver
{
    public function created(Model $model): void
    {
        app(OperationalNotifications::class)->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        app(OperationalNotifications::class)->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        app(OperationalNotifications::class)->record($model, 'deleted');
    }
}
