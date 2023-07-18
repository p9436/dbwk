<?php
  namespace App\Lib;

  /**
   * Validator class provides various validation methods for validating input values.
   */
  class Validator
  {

    function Validator()
    {
      return true;
    }

    /**
     * Validate the input value against the specified validation parameters.
     *
     * @param mixed $value The value to be validated.
     * @param array $params The validation parameters.
     * @return mixed Returns true if the value is valid. Otherwise, returns an error message.
     *
     *
     * $params = array(
     *    'type'          => int | string | mixed | password_confirmation | email | time | filename
     *    'error_type'    => 'Wrong type'
     *    'required'      => true | false (default false)
     *    'default_value' => if require == 1, the variable takes the value value_default; 
     *    'error_empty'   => 'Value is empty',
     *    'value_min'     => 0,
     *    'value_max'     => 10,
     *    'error_outofrange'  => 'The variable can take a value in the range from 0 to 10',
     *    'lenght_min'    => 4,
     *    'lenght_max'    => 20,
     *    'error_lenght_lt' => 'The value must consist of at least 4 characters',
     *    'error_lenght_mt' => 'The value must consist of no more than 20 characters',
     *    'error_password_not_eq'=> 'Passwords do not match',
     *    'password'  => $passwd_confirm,  // if type is password_confirmation
     *    'preg_mask'     => '/[0-9]{3}/',
     *    'error_preg_mask' => '',
     *    'condition' => true|false,
     *    'condition_error' => 'Condition is not met',    
     * )
     *
     *
     * @example
     *
     * $err['UserPasswd'] = $validate->Validate($indata['UserPasswd'], array(
     * 'type'                 => 'password',
     * 'required'             => true,
     * 'error_empty'          => i18nLabel('err_UserPasswd_Empty'),
     * 'lenght_min'           => 6,
     * 'error_lenght_lt'      => i18nLabel('err_UserPasswd_Short'),
     * 'lenght_max'           => 50,
     * 'error_lenght_mt'      => i18nLabel('err_UserPasswd_Long'),
     * ));

     * $err['UserPasswdConf'] = $validate->Validate($indata['UserPasswdConf'], array(
     * 'type'                   => 'password_confirmation',
     * 'required'               => true,
     * 'error_empty'            => i18nLabel('err_UserPasswdConf_Empty'),
     * 'password'               => $indata['UserPasswd'],
     * 'error_password_not_eq'  => i18nLabel('err_UserPasswdConf_NotEq'),
     * 'lenght_min'             => 6,
     * 'error_lenght_lt'        => i18nLabel('err_UserPasswdTooShort'),
     * ));
     *
     */

    public static function Validate($value, $params)
    {
      if ( isset($params['required']) && $params['required'] === true && empty($value))
        return $params['error_empty'];

      if ( !empty($value))
      {
        // Types
        if ( 'int' == $params['type'] && !is_numeric($value))
          return $params['error_type'];

        elseif ('email' == $params['type'] && !preg_match("/^[a-z0-9]+([_\.\-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\.[a-z]{2,}$/i", $value) )
          return $params['error_type'];

        elseif ('url' == $params['type'] && !preg_match("/^http:\/\/([a-z0-9]+([\.-][a-z0-9]+)*)+\.[a-z]{2,}(\/?.*)?$/i", $value) )
          return $params['error_type'];

        elseif ('filename' == $params['type'] && !preg_match("/^\w[\w\.]+$/", $value) )
          return $params['error_type'];

        elseif ( 'password_confirmation' == $params['type'] )
        {
          if ($value !== $params['password'])
            return $params['error_password_not_eq'];
        }
        elseif ('time' == $params['type'] && !(date2unixtime($value) ))
          return $params['error_type'];

        if (!empty($params['preg_mask']) && ! preg_match($params['preg_mask'], $value) )
          return $params['error_preg_mask'];

        if (isset($params['condition']) && ! $params['condition'])
          return $params['error_condition'];

        if (!empty($params['value_min']) && $value < $params['value_min'] )
          return $params['error_outofrange'];

        if (!empty($params['value_max']) && $value > $params['value_max'] )
          return $params['error_outofrange'];

        if (!empty($params['lenght_min']) && strlen($value) < $params['lenght_min'] )
          return $params['error_lenght_lt'];

        if (!empty($params['lenght_max']) && strlen($value) > $params['lenght_max'] )
          return $params['error_lenght_mt'];
      }
      return true;
    }
  }
