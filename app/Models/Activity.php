<?php

namespace App\Models;

use App\Traits\HasUuid;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use HasUuid;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';
}