<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * All application models extend this base.
 *
 * $guarded = [] is BANNED project-wide — it allows mass assignment of any column.
 * Every model must declare explicit $fillable lists. If you need a model that
 * allows any attribute during a one-off seed/factory, use forceFill() explicitly.
 */
abstract class BaseModel extends Model
{
    // Subclasses must declare their own $fillable.
    protected $fillable = [];
}
