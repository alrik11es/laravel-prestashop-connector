<?php
namespace Alr\Laravel\Prestashop\Models;

class Product extends BaseModel
{
    protected $unwritable = ['manufacturer_name', 'quantity'];
}