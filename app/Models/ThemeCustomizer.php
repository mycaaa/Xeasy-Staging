<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeCustomizer extends Model
{
    protected $table = 'tbl_theme_customizer';

    protected $fillable = [
        'user_id',
        'layout',
        'sidebar_user_profile',
        'theme',
        'color_scheme',
        'sidebar_visibility',
        'layout_width',
        'layout_position',
        'topbar_color',
        'sidebar_size',
        'sidebar_view',
        'sidebar_color',
        'sidebar_image',
        'primary_color',
        'preloader',
        'body_image',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sidebar_user_profile' => 'boolean',
    ];

    protected $attributes = [
        'layout' => 'vertical',
        'sidebar_user_profile' => false,
        'theme' => 'default',
        'color_scheme' => 'light',
        'sidebar_visibility' => 'show',
        'layout_width' => 'fluid',
        'layout_position' => 'fixed',
        'topbar_color' => 'light',
        'sidebar_size' => 'lg',
        'sidebar_view' => 'default',
        'sidebar_color' => 'light',
        'sidebar_image' => 'none',
        'primary_color' => 'default',
        'preloader' => 'disable',
        'body_image' => 'none',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}