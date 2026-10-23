<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicProgram extends Model
{
    use HasFactory;

    protected $fillable = ['college_id', 'name', 'majors'];

    protected function casts(): array
    {
        return [
            'majors' => 'array',
        ];
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }
}
