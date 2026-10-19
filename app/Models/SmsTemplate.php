<?php

namespace App\Models;

use Database\Factories\SmsTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'body'])]
class SmsTemplate extends Model
{
    /** @use HasFactory<SmsTemplateFactory> */
    use HasFactory;
}
