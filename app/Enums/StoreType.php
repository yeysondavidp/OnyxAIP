<?php

namespace App\Enums;

enum StoreType: string
{
    case ConceptStore              = 'concept_store';
    case Franchise                 = 'franchise';
    case DepartmentStoreConcession = 'department_store_concession';
    case PopUp                     = 'pop_up';
    case Other                     = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ConceptStore              => 'Concept Store',
            self::Franchise                 => 'Franchise',
            self::DepartmentStoreConcession => 'Department Store Concession',
            self::PopUp                     => 'Pop-Up',
            self::Other                     => 'Other',
        };
    }
}
