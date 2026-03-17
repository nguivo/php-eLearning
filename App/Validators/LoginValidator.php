<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * LoginValidator
 *
 * Validates the login form before AuthService::login() is called.
 * Only checks presence and format — does NOT check credentials against
 * the database. That is AuthService's job.
 *
 * Usage in AuthController:
 *
 *   public function login(Request $request, Response $response): void
 *   {
 *       $validator = new LoginValidator();
 *
 *       if (!$validator->validate($this->all())) {
 *           $this->flash('errors', $validator->errors());
 *           $this->flash('old',    ['email' => $this->input('email')]); // never re-flash password
 *           return $this->redirectBack();
 *       }
 *
 *       // AuthService handles the "wrong password" case — that is a
 *       // business rule, not a format validation rule
 *       $user = $this->authService->login(
 *           $validator->validated()['email'],
 *           $validator->validated()['password']
 *       );
 *
 *       if (!$user) {
 *           $this->flash('error', 'Invalid email or password.');
 *           return $this->redirectBack();
 *       }
 *
 *       $this->redirect('/dashboard');
 *   }
 */
class LoginValidator extends Validator
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required'    => 'Please enter your email address.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }
}