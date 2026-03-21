<?php

if (! function_exists('getErrorMessages')) {
    function getErrorMessages($errors)
    {
        return collect($errors)
            ->flatten()
            ->filter()
            ->implode(' ');
    }
}

