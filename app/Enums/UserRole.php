<?php
namespace App\Enums;

enum UserRole: int
{
    case SuperAdmin = 1; 
    case Marketer = 2;    // Маркетолог
    case SalesManager = 3;   // Логист
}