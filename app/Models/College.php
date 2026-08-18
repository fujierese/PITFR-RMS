<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'abbreviation', 'description'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
