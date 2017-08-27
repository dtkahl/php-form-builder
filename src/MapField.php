<?php namespace Dtkahl\FormBuilder;

use Dtkahl\ArrayTools\Map;

abstract class MapField extends AbstractField implements \ArrayAccess
{

    /** @var Map|AbstractField[] */
    protected $children;

    public function __construct(array $options = [])
    {
        $this->children = new Map;
        parent::__construct($options);
        $this->setUp();
    }

    /**
     * define the child fields here
     */
    abstract public function setUp() : void;

    /**
     * @param string $name
     * @param null|AbstractField $child
     * @param array $options
     * @return AbstractField
     */
    protected function setChild(string $name, ?AbstractField $child = null, array $options = []) : AbstractField
    {
        if ($child === null) {
            $child = new Field;
        }
        $this->children->set($name, $child);
        $child->setName($name);
        $child->options()->merge($options);

        return $child;
    }

    /**
     * @param string $name
     * @return $this|self
     */
    protected function removeChild(string $name) : self
    {
        $this->children->remove($name);
        return $this;
    }

    /**
     * @param $name
     * @return AbstractField
     */
    public function getChild(string $name) : AbstractField
    {
        $child = $this->children->get($name);
        if ($child instanceof AbstractField) {
            return $child;
        }
        throw new \RuntimeException("Unknown field '$name'.");
    }


    /**
     * @return MapField[]
     */
    public function children() : array
    {
        return $this->children->toArray();
    }

    /**
     * @param mixed|null $default
     * @return array
     */
    public function toValue($default) : array
    {
        return $this->children->copy()->map(function ($name, $child) {
            /** @var AbstractField $child */
            return $child->getValue();
        })->toArray();
    }

    /**
     * @param array $data
     * @return void
     */
    protected function fromValue($data) : void
    {
        $data = (array) $data;
        foreach ($data as $name=>$field_data) {
            $this->getChild($name)->setValue($field_data);
        }
    }

    /**
     * @return array|bool
     */
    public function validate()
    {
        $this->valid = $this->children->copy()->filter(function (string $name, AbstractField $child) {
                return $this->checkChildConditions($name) && !$child->validate();
            })->count() == 0 && parent::validate();
        return $this->valid;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function checkChildConditions(string $name)
    {
        $child = $this->getChild($name);
        $conditions = $child->getOption("conditions", []);
        foreach ($conditions as $condition) {
            if (!$this->checkCondition($condition)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $condition
     * @return bool
     */
    protected function checkCondition(array $condition)
    {
        if (count($condition) < 3) {
            throw new \InvalidArgumentException("Field Condition need to have atleast 3 items.");
        }
        [$name, $comparator, $value] = $condition;
        $actual_field = $this->getChild($name)->getValue();
        switch ($comparator) {
            case '==':
                return $actual_field == $value;
            case '!=':
                return $actual_field != $value;
            case '===':
                return $actual_field === $value;
            case '!==':
                return $actual_field !== $value;
            case 'in':
                return in_array($actual_field, $value);
            case 'not in':
                return !in_array($actual_field, $value);
            default:
                throw new \InvalidArgumentException("Unknown comparator '$comparator'.");
        }
    }


    /**
     * @return array
     */
    public function getMessages() : array
    {
        $messages = [];
        foreach ($this->children->toArray() as $name=>$child) {
            /** @var AbstractField $child */
             if (!$child->isValid()) {
                 $messages[$name] = $child->getMessages();
             }
        }
        return $messages;
    }

    /**
     * @param string $offset
     * @return AbstractField
     */
    public function offsetGet($offset)
    {
        return $this->getChild($offset);
    }

    /**
     * @param string $offset
     * @param AbstractField $value
     */
    public function offsetSet($offset, $value)
    {
        $this->setChild($offset, $value);
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->children->has($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        $this->removeChild($offset);
    }

    /**
     * @return array
     */
    public function toSerializedArray()
    {
        $data = parent::toSerializedArray();
        $data["map"] = array_values($this->children->copy()->map(function ($name, AbstractField $child) {
            return $child->toSerializedArray();
        })->toArray());
        return $data;
    }

}