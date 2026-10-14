<?php

namespace App\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validate data with a web FormRequest's own rules, messages and after-hooks, with its route
 * parameters bound — so a non-form path (MCP tools, PM verification) never drifts from the form.
 */
final class FormRequestRules
{
    /**
     * @param  class-string<FormRequest>  $formRequest
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(string $formRequest, array $data, array $routeParameters = []): array
    {
        $form  = $formRequest::create('/', 'POST', $data);
        $route = (new Route('POST', '/', []))->bind($form);

        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $form->setRouteResolver(fn () => $route);

        // rules() is declared by each FormRequest, not by the base class.
        $rules     = method_exists($form, 'rules') ? $form->rules() : [];
        $validator = Validator::make($data, $rules, $form->messages(), $form->attributes());

        if (method_exists($form, 'withValidator')) {
            $form->withValidator($validator);
        }

        return $validator->validate();
    }
}
