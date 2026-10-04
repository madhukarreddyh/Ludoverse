<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Device hashes that may never sign up or log in again.
 */
class BannedDevice extends Model
{
    protected $fillable = ['device_hash', 'reason'];
}
