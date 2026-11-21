<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomepageDataObserver
{
    public function saved(Model $model): void
    {
        Cache::forget('homepage-data');
    }

    public function deleted(Model $model): void
    {
        Cache::forget('homepage-data');
    }
}
