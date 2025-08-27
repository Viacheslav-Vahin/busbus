<?php
// app/Models/CompanyProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = ['name','edrpou','iban','bank','addr','vat'];
}
