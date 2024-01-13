<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DataVending extends Model
{
    use HasFactory;

    protected $table = 'data_vending';

    protected $fillable = [
        'uuid',
        'encryption_key',
        'student_id',
        'reference',
        'phone_number',
        'ip_address',
        'network',
        'whatsapp',
        'status',
        'api_response'
    ];

    public static function boot() {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = Str::uuid();
            $model->encryption_key = Str::uuid();
            $model->reference = generate_string(12, 'mixed', 'upper', 'OAUCSC_');
        });
    }
    
    public function student() {
        return $this->belongsTo(Student::class);
    }
}
