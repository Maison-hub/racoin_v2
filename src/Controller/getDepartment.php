<?php

namespace Controller;

use Model\Departement;

class getDepartment
{
    protected $departments = array();

    public function getAllDepartments(): array
    {
        return Departement::orderBy('nom_departement')->get()->toArray();
    }
}
