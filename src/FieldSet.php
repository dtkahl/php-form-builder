<?php namespace Dtkahl\FormBuilder;

use Dtkahl\ArrayTools\Map;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator;

// TODO custom messages/translations
abstract class FieldSet
{
    /** @var Map|Field[] */
    protected $fields;

    /** @var  Map|FieldSet[] */
    protected $field_sets;

    /** @var Validator[] */
    protected $validators;
    
    protected $messages;

    public function __construct()
    {
        $this->fields = new Map;
        $this->field_sets = new Map;
        $this->validators = new Map;
        $this->messages = new Map;
        $this->setUp();
    }

    abstract public function setUp();

    /**
     * Set up the Field Validators here separately. This is only called when validation actually runs so you can use
     * field values as conditions.
     */
    abstract public function setUpValidators();

    /**
     * @param string $name
     * @param Field $element
     * @return $this
     */
    protected function setField(string $name, Field $element)
    {
        $this->field_sets->remove($name); // because name must be unique
        $this->fields->set($name, $element);
        $element->setLabel($name);
        return $this;
    }

    /**
     * @param $name
     * @return Field
     */
    public function getField(string $name) // TODO array/dot notation to access sub field set fields
    {
        $field = $this->fields->get($name);
        if ($field instanceof Field) {
            return $field;
        }
        throw new \RuntimeException("Unknown field '$name'.");
    }

    /**
     * @param $name
     * @param FieldSet $field_set
     * @return $this
     */
    public function setFieldSet(string $name, FieldSet $field_set)
    {
        $this->fields->remove($name); // because name must be unique
        $this->field_sets->set($name, $field_set);
        return $this;
    }

    /**
     * @param string $name
     * @return FieldSet
     */
    public function getFieldSet(string $name)
    {
        $field_set = $this->field_sets->get($name);
        if ($field_set instanceof FieldSet) {
            return $field_set;
        }
        throw new \RuntimeException("Unknown field set '$name'.");
    }

    /**
     * @param $name
     * @param $label
     * @return $this
     */
    public function setLabel(string $name, string $label)
    {
        $this->getField($name)->setLabel($label);
        return $this;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getValue(string $name)
    {
        return $this->getField($name)->getValue();
    }

    /**
     * @param $name
     * @param Validator $validator
     * @return $this
     */
    public function setValidator(string $name, Validator $validator)
    {
        $this->validators->set($name, $validator);
        return $this;
    }

    /**
     * @param $name
     * @return Validator
     */
    public function getValidator(string $name)
    {
        return $this->validators->get($name);
    }

    /**
     * @param array $data
     * @return mixed|void
     */
    public function hydrate(array $data)
    {
        foreach ($data as $name=>$field_data) {
            $field = $this->fields->get($name);
            if ($field instanceof Field) {
                $field->setValue($field_data);
                continue;
            }
            $field_set = $this->field_sets->get($name);
            if ($field_set instanceof FieldSet) {
                $field_set->hydrate($field_data);
            }
        }
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        // (re)initialize validators, allow conditions based on values
        // reset messages
        $this->validators = new Map;
        $this->messages = new Map;
        $this->setUpValidators();

        $invalid_fields = $this->fields->copy()->filter(function (string $name, Field $field) {
            $validator = $this->validators->get($name);
            if ($validator instanceof Validator) {
                $validator->setName($name);
                try {
                    $validator->assert($field->getValue());
                } catch (NestedValidationException $e) {
                    $this->messages->set($name, $e->getMessages());
                    return true;
                }
            }
        });
        $invalid_field_sets = $this->field_sets->each(function (string $name, FieldSet $field_set) {
            if (!$field_set->isValid()) {
                $this->messages->set($name, $field_set->getMessages());
                return true;
            }
        });
        return $invalid_fields->count() == 0 && $invalid_field_sets->count() == 0;
    }

    /**
     * @param $name
     * @return array
     */
    public function getMessages(string $name = null)
    {
        if (is_null($name)) {
            return $this->messages->toArray();
        }
        return $this->messages->get($name, []);
    }
}