<?php

use Carbon\Carbon;

function helper_umur($tanggal_lahir){
    return Carbon::parse($tanggal_lahir)->age;
}
