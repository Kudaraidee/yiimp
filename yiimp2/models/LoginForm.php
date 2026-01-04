<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property-read User|null $user
 *
 */
class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;

    private $_user = false;


    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     * 
     * Implements session ID regeneration after authentication (Requirement 3.1)
     * to prevent session fixation attacks.
     * 
     * @return bool whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            // Regenerate session ID after successful authentication (Requirement 3.1)
            // This prevents session fixation attacks by invalidating the old session ID
            // while preserving session data (Requirements 3.2, 3.3, 3.4)
            try {
                $oldSessionId = Yii::$app->session->getId();
                
                // Regenerate session ID (Requirements 3.1, 3.3, 3.4, 3.5)
                Yii::$app->session->regenerateID();
                
                $newSessionId = Yii::$app->session->getId();
                
                // Log session regeneration for security audit (Requirement 6.1)
                Yii::info([
                    'event' => 'session_regeneration',
                    'reason' => 'successful_authentication',
                    'username' => $this->username,
                    'old_session_id' => substr($oldSessionId, 0, 20) . '...',
                    'new_session_id' => substr($newSessionId, 0, 20) . '...',
                    'session_id_changed' => $oldSessionId !== $newSessionId,
                    'ip_address' => Yii::$app->request->getUserIP(),
                    'user_agent' => Yii::$app->request->getUserAgent(),
                ], 'session.regenerate');
                
                // Verify session ID actually changed
                if ($oldSessionId === $newSessionId) {
                    Yii::warning([
                        'event' => 'session_regeneration_failed',
                        'reason' => 'session_id_unchanged',
                        'username' => $this->username,
                        'session_id' => substr($oldSessionId, 0, 20) . '...',
                    ], 'session.regenerate');
                }
            } catch (\Exception $e) {
                // Log regeneration failure but don't block login
                Yii::error([
                    'event' => 'session_regeneration_error',
                    'reason' => 'exception_during_regeneration',
                    'username' => $this->username,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ], 'session.regenerate');
            }
            
            // Proceed with login
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600*24*30 : 0);
        }
        return false;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }
}
