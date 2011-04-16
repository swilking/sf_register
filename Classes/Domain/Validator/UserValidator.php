<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Sebastian Fischer <typo3@evoweb.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * A Uservalidator
 *
 * @scope singleton
 */
class Tx_SfRegister_Domain_Validator_UserValidator extends Tx_Extbase_Validation_Validator_AbstractValidator {
	/**
	 * @var Tx_Extbase_Object_ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var array
	 */
	protected $settings = NULL;

	/**
	 * @var  Tx_SfRegister_Validation_ValidatorResolver
	 */
	protected $validatorResolver;

	/**
	 * @var string
	 */
	protected $currentFieldName = '';

	/**
	 * @var array
	 */
	protected $currentValidatorOptions = array();

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->initializeObjectManager();
		$this->initializeSettings();
		$this->initializeValidatorResolver();
	}

	/**
	 * Initializes the Object framework.
	 *
	 * @return void
	 * @see initialize()
	 */
	protected function initializeObjectManager() {
		$this->objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_ObjectManager');
	}

	/**
	 * Initialize settings
	 *
	 * @return array
	 */
	protected function initializeSettings() {
		if ($this->settings == NULL) {
			$configurationManager = $this->objectManager->get('Tx_Extbase_Configuration_ConfigurationManagerInterface');
			$this->settings = $configurationManager->getConfiguration(Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS);
		}

		return $this->settings;
	}

	/**
	 * Initialize validator resolver
	 *
	 * @return void
	 */
	protected function initializeValidatorResolver() {
		$this->validatorResolver = $this->objectManager->get('Tx_SfRegister_Validation_ValidatorResolver');
	}


	/**
	 * Add an error with message and code to the property errors
	 *
	 * @param array $propertyName name of the property to add the error to
	 * @param string $message Message to be shwon
	 * @param string $code Error code to identify the error
	 * @return void
	 */
	protected function addErrorsForProperty($propertyName, $message, $code) {
		if (!isset($this->errors[$propertyName])) {
			$this->errors[$propertyName] = t3lib_div::makeInstance('Tx_Extbase_Validation_PropertyError', $propertyName);
		}

		$errors = array(
			t3lib_div::makeInstance('Tx_Extbase_Validation_Error', $message, $code)
		);

		$this->errors[$propertyName]->addErrors($errors);
	}

	/**
	 * If the given user are valid
	 *
	 * @param object $object
	 * @return boolean
	 */
	public function isValid($object) {
		$result = TRUE;

		if (!is_object($object)) {
			$this->addError(Tx_Extbase_Utility_Localization::translate('error.notvalidatable', 'SfRegister'), 1301599551);
			$result = FALSE;
		} else {
			$result = $this->validateRules($object);
		}

		return $result;
	}

	/**
	 * Validate all rules
	 *
	 * @param object $object
	 * @return boolean
	 */
	protected function validateRules($object) {
		$result = TRUE;

		foreach ($this->settings['validation'][$this->options['type']] as $fieldName => $rule) {
			$methodName = 'get' . ucfirst($fieldName);

			if (!method_exists($object, $methodName)) {
				$this->addError(Tx_Extbase_Utility_Localization::translate('error.notexists', 'SfRegister'), 1301599575);
				$result = FALSE;
			} else {
				$this->currentFieldName = $fieldName;
				$fieldValue = $object->{$methodName}();

				if (is_array($rule)) {
					$result = $this->validateRuleArray($fieldValue, $rule) && $result ? TRUE : FALSE;
				} else {
					$result = $this->validateValueWithRule($fieldValue, $rule) && $result ? TRUE : FALSE;
				}
			}
		}

		return $result;
	}

	/**
	 * Validate rules until one of them failes and then stop validating any further
	 *
	 * @param mixed $fieldValue
	 * @param array $rules
	 * @return boolean
	 */
	protected function validateRuleArray($fieldValue, Array $rules) {
		$result = TRUE;

		foreach ($rules as $rule) {
			$result = $this->validateValueWithRule($fieldValue, $rule) && $result ? TRUE : FALSE;

			if (!$result) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Validate value with rule
	 *
	 * @param mixed $value
	 * @param string $rule
	 * @return boolean
	 */
	protected function validateValueWithRule($value, $rule) {
		$result = TRUE;

		$validator = $this->getValidator($rule);
		if (method_exists($validator, 'setFieldname')) {
			$validator->setFieldname($this->currentFieldName);
		}

		if ($validator instanceof Tx_Extbase_Validation_Validator_ValidatorInterface AND
				!$validator->isValid($value)) {

			$this->mergeErrorsIntoLocalErrors($validator->getErrors());
			$result = FALSE;
		}

		return $result;
	}

	/**
	 * Merge error into local errors
	 *
	 * @param array $errors
	 * @return void
	 */
	protected function mergeErrorsIntoLocalErrors($errors) {
		foreach ($errors as $error) {
			$localizedFieldName = Tx_Extbase_Utility_Localization::translate($this->currentFieldName, 'SfRegister');

			$errorMessage = $error->getMessage();
			$localizedErrorCode = Tx_Extbase_Utility_Localization::translate('error.' . $error->getCode(), 'SfRegister');
			$localizedErrorMessage = $localizedErrorCode ? $localizedErrorCode : $errorMessage;

			$markers = array_merge((array) $localizedFieldName, $this->currentValidatorOptions);
			$messageWithReplacedMarkers = vsprintf($localizedErrorMessage, $markers);

			$this->addErrorsForProperty(
				$this->currentFieldName,
				$messageWithReplacedMarkers,
				$error->getCode()
			);
		}
	}

	/**
	 * Parse the rule and instanciate an validator with the name and the options
	 *
	 * @param string $rule
	 * @return Tx_Extbase_Validation_Validator_ValidatorInterface
	 */
	protected function getValidator($rule) {
		$currentValidator = $this->parseRule($rule);
		$this->currentValidatorOptions = (array) $currentValidator['validatorOptions'];
		
		$validatorObject = $this->validatorResolver->createValidator(
			$currentValidator['validatorName'],
			$this->currentValidatorOptions
		);

		return $validatorObject;
	}

	/**
	 * Parse rule
	 *
	 * @param string $rule
	 * @return void
	 */
	protected function parseRule($rule) {
		$parsedRules = $this->validatorResolver->getParsedValidatorAnnotation($rule);
		return current($parsedRules['validators']);
	}
}

?>