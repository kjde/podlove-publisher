<?php
namespace Podlove\Constraint;

use \Podlove\Model;

abstract class Constraint {

	/**
	 * Severities.
	 *
	 * SEVERITY_WARNING: 
	 * 	Something is not quite right. Important services are probably available
	 * 	but the issue should be adressed.
	 * SEVERITY_CRITICAL:
	 * 	Must be solved as soon as possible.
	 */
	const SEVERITY_WARNING = 'warning';
	const SEVERITY_CRITICAL = 'critical';

	/**
	 * The resource which is validated.
	 * Must have an 'id' attribute.
	 * 
	 * @var object
	 */
	protected $resource = null;

	/**
	 * Outputs a description of the constraint violation.
	 *
	 * It should explain what's wrong and should contain steps
	 * to resolve the issue. May contain HTML.
	 */
	public abstract function the_description();

	/**
	 * Outputs a short title of the constraint violation.
	 */
	public abstract function the_title();

	/**
	 * Validates the constraint.
	 *
	 * Must be implemented by the inheriting class.
	 * Contains the actual check for validity.
	 * 
	 * @return bool
	 */
	public abstract function isValid();

	public function __construct($feed) {
		$this->setResource($feed);
	}

	/**
	 * Validates the constraint.
	 */
	final public function validate() {
		if ($this->isValid()) {
			$this->handleValidationSuccess();
		} else {
			$this->handleValidationFailure();
		}
	}

	/**
	 * Check if a violation has been reported.
	 * @return bool
	 */
	final protected function isViolated() {
		return !!$this->findViolation();
	}

	final protected function setResource($resource) {
		$this->resource = $resource;
	}

	/**
	 * Mark violation als resolved.
	 */
	final private function resolve() {
		$violation = $this->findViolation();
		$violation->resolve();
	}

	/**
	 * Set last_checked_at date for Violation to now.
	 */
	final private function updateViolation() {
		$violation = $this->findViolation();
		$violation->last_checked_at = date("Y-m-d H:i:s");
		$violation->save();
	}

	final private function createViolation() {
		$violation = new Model\Violation;
		$violation->constraint_class = get_class($this);
		$violation->severity = static::SEVERITY;
		$violation->scope = static::SCOPE;

		if (static::SCOPE !== 'podcast')
			$violation->scope_resource_id = $this->resource->id;
		
		$violation->occured_at = date("Y-m-d H:i:s");
		$violation->last_checked_at = date("Y-m-d H:i:s");
		$violation->save();
	}

	final private function findViolation() {
		return Model\Violation::find_one_by_where(
			sprintf(
				'constraint_class = "%s" AND scope = "%s" AND scope_resource_id = "%d" AND resolved_at IS NULL',
				get_class($this),
				static::SCOPE,
				$this->resource->id
			)
		);
	}

	final private function handleValidationSuccess() {
		if ($this->isViolated())
			$this->resolve();
	}
	
	final private function handleValidationFailure() {
		if ($this->isViolated()) {
			$this->updateViolation();
		} else {
			$this->createViolation();
		}
	}

}