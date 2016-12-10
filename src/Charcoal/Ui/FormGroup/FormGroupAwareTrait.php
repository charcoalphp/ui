<?php

namespace Charcoal\Ui\FormGroup;

use \RuntimeException;
use \InvalidArgumentException;

// From 'charcoal-factory'
use \Charcoal\Factory\FactoryInterface;

// From 'charcoal-ui'
use \Charcoal\Ui\Form\FormInterface;
use \Charcoal\Ui\FormInput\FormInputAwareInterface;
use \Charcoal\Ui\FormGroup\FormGroupAwareInterface;
use \Charcoal\Ui\FormGroup\FormGroupInterface;

/**
 * Provides an implementation of {@see \Charcoal\Ui\FormGroup\FormGroupAwareInterface}.
 */
trait FormGroupAwareTrait
{
    /**
     * The form's display mode for field groups.
     *
     * @var string|null
     */
    protected $groupDisplayMode;

    /**
     * The form's field groups.
     *
     * @var FormGroupInterface[]|null
     */
    protected $groups;

    /**
     * Store the form's group factory instance.
     *
     * @var FactoryInterface|null
     */
    protected $formGroupFactory;

    /**
     * Store the form's group callback.
     *
     * The callback is applied each form group during rendering.
     *
     * @var callable|null
     */
    private $groupCallback;

    /**
     * Retrieve the default form group class name.
     *
     * @return string
     */
    abstract public function defaultGroupType();

    /**
     * Set the form group factory.
     *
     * @param  FactoryInterface $factory A factory, to create customized form gorup objects.
     * @return FormGroupAwareInterface
     */
    public function setFormGroupFactory(FactoryInterface $factory)
    {
        $this->formGroupFactory = $factory;

        return $this;
    }

    /**
     * Retrieve the form group factory.
     *
     * @throws RuntimeException If the form group factory object was not set / injected.
     * @return FactoryInterface
     */
    public function formGroupFactory()
    {
        if ($this->formGroupFactory === null) {
            throw new RuntimeException(
                'Form group factory was not set.'
            );
        }

        return $this->formGroupFactory;
    }

    /**
     * Set the form group callback.
     *
     * @param  mixed $callback The callback routine.
     * @throws InvalidArgumentException If the given routine is not callable.
     * @return FormGroupAwareInterface
     */
    public function setGroupCallback($callback)
    {
        if ($callback === null) {
            $this->groupCallback = null;
            return $this;
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException(sprintf(
                'The form group callback must be callable, received %s',
                (is_object($callback) ? get_class($callback) : gettype($callback))
            ));
        }

        $this->groupCallback = $callback;

        return $this;
    }

    /**
     * Set the form groups.
     *
     * @param  array $groups A collection of form group structures.
     * @return FormGroupAwareInterface
     */
    public function setGroups(array $groups)
    {
        $this->groups = [];

        foreach ($groups as $groupIdent => $groupStruct) {
            $this->addGroup($groupIdent, $groupStruct);
        }

        return $this;
    }

    /**
     * Add a form group.
     *
     * @param  string $groupIdent  The group identifier.
     * @param  mixed  $groupStruct The group object or structure.
     * @throws InvalidArgumentException If the identifier is not a string or the group is invalid.
     * @return FormGroupAwareInterface
     */
    public function addGroup($groupIdent, $groupStruct)
    {
        if (!is_string($groupIdent)) {
            throw new InvalidArgumentException(
                'Group identifier must be a string'
            );
        }

        if ($groupStruct === false || $groupStruct === null) {
            return $this;
        }

        if ($groupStruct instanceof FormGroupInterface) {
            $group = $groupStruct;
            $this->resolveFormFor($group);
            $group->setIdent($groupIdent);
        } elseif (is_array($groupStruct)) {
            if (isset($groupStruct['ident'])) {
                $groupIdent = $groupStruct['ident'];
            } else {
                $groupStruct['ident'] = $groupIdent;
            }

            if (!isset($groupStruct['type'])) {
                $groupStruct['type'] = $this->defaultGroupType();
            }

            $group = $this->formGroupFactory()->create($groupStruct['type']);
            $this->resolveFormFor($group);
            $group->setData($groupStruct);
        } else {
            throw new InvalidArgumentException(sprintf(
                'Form group must be an instance of %s or an array of form group options, received %s',
                FormGroupInterface::class,
                (is_object($groupStruct) ? get_class($groupStruct) : gettype($groupStruct))
            ));
        }

        $this->groups[$groupIdent] = $group;

        return $this;
    }

    /**
     * Retrieve the form groups.
     *
     * @todo   Implement AdminWidget::loginUrl()
     * @param  callable $groupCallback Optional callback applied to each form group.
     * @return FormGroupInterface[]|Generator
     */
    public function groups(callable $groupCallback = null)
    {
        if (empty($this->groups)) {
            return;
        }

        $groups = $this->groups;

        uasort($groups, [ $this, 'sortFormGroupsByPriority' ]);

        $groupCallback = (isset($groupCallback) ? $groupCallback : $this->groupCallback);

        $i = 1;
        foreach ($groups as $group) {
            if (!$group->active()) {
                continue;
            }

            // Test Form Group vs. ACL roles
            $authUser = $this->authenticator()->authenticate();
            if (!$this->authorizer()->userAllowed($authUser, $group->requiredAclPermissions())) {
                header('HTTP/1.0 403 Forbidden');
                header('Location: '.$this->adminUrl().'login');
                continue;
            }

            $this->resolveL10nDisplayModeFor($group);
            $this->resolveGroupDisplayModeFor($group);

            if ($groupCallback) {
                $groupCallback($group);
            }

            $GLOBALS['widget_template'] = $group->template();

            if ($this->isTabbable() && $i > 1) {
                $group->isHidden = true;
            }
            $i++;

            yield $group;
        }
    }

    /**
     * Resolve the group's related form.
     *
     * @param  FormGroupInterface $group A form group.
     * @return FormGroupAwareInterface
     */
    private function resolveFormFor(FormGroupInterface &$group)
    {
        if ($this instanceof FormGroupInterface) {
            $group->setForm($this->form());
        } elseif ($this instanceof FormInterface) {
            $group->setForm($this);
        }

        return $this;
    }

    /**
     * Resolve the group's l10n display mode.
     *
     * @param  FormGroupInterface $group A form group.
     * @return FormGroupAwareInterface
     */
    private function resolveL10nDisplayModeFor(FormGroupInterface &$group)
    {
        if (($group instanceof FormInputAwareInterface) && ($this instanceof FormInputAwareInterface)) {
            if (!$group->hasL10nDisplayMode() && $this->hasL10nDisplayMode()) {
                $group->setL10nDisplayMode($this->l10nDisplayMode());
            }
        }

        return $this;
    }

    /**
     * Resolve the group's display mode.
     *
     * @param  FormGroupInterface $group A form group.
     * @return FormGroupAwareInterface
     */
    private function resolveGroupDisplayModeFor(FormGroupInterface &$group)
    {
        if (($group instanceof FormGroupAwareInterface) && ($this instanceof FormGroupAwareInterface)) {
            if (!$group->hasGroupDisplayMode() && $this->hasGroupDisplayMode()) {
                $group->setGroupDisplayMode($this->groupDisplayMode());
            }
        }

        return $this;
    }

    /**
     * Count the number of form groups.
     *
     * @return integer
     */
    public function numGroups()
    {
        return count($this->groups);
    }

    /**
     * Determine if the form has any groups.
     *
     * @return boolean
     */
    public function hasGroups()
    {
        return ($this->numGroups() > 0);
    }

    /**
     * Determine if the form has a given group.
     *
     * @param  string $groupIdent The group identifier to look up.
     * @throws InvalidArgumentException If the group identifier is invalid.
     * @return boolean
     */
    public function hasGroup($groupIdent)
    {
        if (!is_string($groupIdent)) {
            throw new InvalidArgumentException(
                'Group identifier must be a string'
            );
        }

        return isset($this->groups[$groupIdent]);
    }

    /**
     * Static comparison function used by {@see uasort()}.
     *
     * @param  FormGroupInterface $a Form Group A.
     * @param  FormGroupInterface $b Form Group B.
     * @return integer Sorting value: -1 or 1
     */
    protected static function sortFormGroupsByPriority(
        FormGroupInterface $a,
        FormGroupInterface $b
    ) {
        $a = $a->priority();
        $b = $b->priority();

        return ($a < $b) ? (-1) : 1;
    }



    // Layout
    // =========================================================================

    /**
     * Retrieve the display mode for handling field groups.
     *
     * @return string
     */
    public function groupDisplayMode()
    {
        if ($this->groupDisplayMode === null) {
            $this->setGroupDisplayMode($this->defaultGroupDisplayMode());
        }

        return $this->groupDisplayMode;
    }

    /**
     * Set the display mode for handling field groups.
     *
     * Possible values are:
     * - `vertical` (default)
     * - `tabs`
     *
     * @param  string $mode The group display mode.
     * @throws InvalidArgumentException If the display mode is not a string.
     * @return FormGroupAwareInterface
     */
    public function setGroupDisplayMode($mode)
    {
        if (!is_string($mode)) {
            throw new InvalidArgumentException(sprintf(
                'The group display mode must be a string, received %s',
                (is_object($mode) ? get_class($mode) : gettype($mode))
            ));
        }

        if ($mode === 'tab' || $mode === 'tabbed') {
            $mode = 'tabs';
        }

        $this->groupDisplayMode = $mode;

        return $this;
    }

    /**
     * Determine if a group display mode is set.
     *
     * @return boolean
     */
    public function hasGroupDisplayMode()
    {
        return !!$this->groupDisplayMode;
    }

    /**
     * Retrieve the default display mode for field groups.
     *
     * @return string
     */
    private function defaultGroupDisplayMode()
    {
        return 'vertical';
    }

    /**
     * Determine if field groups are to be displayed as tabbable panels.
     *
     * @return boolean
     */
    public function isTabbable()
    {
        return ($this->groupDisplayMode() === 'tabs');
    }
}
