<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * ResetPasswordValidator
 *
 * Validates the password reset form (new password + confirmation).
 * Used by AuthController::resetPassword() after the token has already
 * been verified by AuthService.
 *
 * Demonstrates the 'confirmed' rule — password must match password_confirmation.
 *
 * Usage in AuthController:
 *
 *   public function resetPassword(Request $request, Response $response): void
 *   {
 *       $token     = $request->getRouteParam('token');
 *       $validator = new ResetPasswordValidator();
 *
 *       if (!$validator->validate($this->all())) {
 *           $this->flash('errors', $validator->errors());
 *           return $this->redirectBack();
 *       }
 *
 *       $this->authService->resetPassword($token, $validator->validated()['password']);
 *       $this->flash('success', 'Password updated. Please log in.');
 *       return $this->redirect('/login');
 *   }
 */
class ResetPasswordValidator extends Validator
{
    protected function rules(): array
    {
        return [
            'password'              => 'required|min:8|max:72|confirmed',
            'password_confirmation' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'password.required'              => 'Please enter a new password.',
            'password.min'                   => 'Your new password must be at least 8 characters.',
            'password.confirmed'             => 'The passwords don\'t match.',
            'password_confirmation.required' => 'Please confirm your new password.',
        ];
    }
}