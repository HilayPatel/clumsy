<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Clumsy\CMS\Facades\Clumsy;

/*
|--------------------------------------------------------------------------
| Delete button
|--------------------------------------------------------------------------
|
| This macro creates a form with only a submit button. 
| We'll use it to generate forms that will post to a certain url with the
| DELETE method, following REST principles.
|
*/
Form::macro('delete', function($resource_type, $id) {

    $form_parameters = array(
        'method' => "DELETE",
        'url'    => URL::route(Clumsy::prefix().".$resource_type.destroy", $id),
        'class'  => "delete-form btn-outside pull-left $resource_type",
    );
 
    return Form::open($form_parameters).Form::close();
});