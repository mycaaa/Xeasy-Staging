<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class EmailTemplate extends Model
{
    use LogsActivity;

    protected $table = 'eml_email_templates';

    protected $fillable = ['name', 'subject', 'body_html', 'body_text', 'is_active', 'category', 'created_by', 'updated_by'];

    public function variables()
    {
        return $this->hasMany(TemplateVariable::class);
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Email Template {$eventName}")
            ->useLogName('email_template')
            ->logAll();
    }
}
