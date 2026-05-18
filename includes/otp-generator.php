<?php

function generateOTP($length = 6) {
    $otp = "";

    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }

    return $otp;
}