<?php

namespace Charcoal\Ui\Form;

/**
 * Defines a form.
 *
 * A form contains interactive controls to submit information to a web server,
 *
 * Implementation, as trait, provided by {@see \Charcoal\Ui\Form\FormTrait}.
 */
interface FormInterface
{
    /** @const string The HTTP 'GET' method. */
    const HTTP_METHOD_GET  = 'get';

    /** @const string The HTTP 'POST' method. */
    const HTTP_METHOD_POST = 'post';

    /** @const string The default HTTP method. */
    const DEFAULT_HTTP_METHOD = self::HTTP_METHOD_GET;

    /**
     * Set the program that processes the form information.
     *
     * @param  string $action The form action, typically a URI.
     * @return FormInterface Chainable
     */
    public function setAction($action);

    /**
     * Retrieve the program that processes the form information.
     *
     * @return string
     */
    public function action();

    /**
     * Set the HTTP method used to submit the form.
     *
     * Possible values are:
     * - {@see FormInterface::HTTP_METHOD_POST `post`}:
     *   Corresponds to the HTTP POST method ; form data are included
     *   in the body of the form and sent to the server.
     * - {@see FormInterface::HTTP_METHOD_GET `get`}:
     *   Corresponds to the HTTP GET method; form data are appended
     *   to the {@see FormInterface::action()} attribute URI as a query string,
     *   and the resulting URI is sent to the server.
     *
     * @param  string $method The HTTP method, usually one of GET or POST.
     * @return FormInterface Chainable
     */
    public function setMethod($method);

    /**
     * Retrieve the HTTP method used to submit the form.
     *
     * @return string
     */
    public function method();

    /**
     * Set the form's dataset.
     *
     * @param  array $data Key/value pairs representing form fields and their values.
     * @return FormInterface Chainable
     */
    public function setFormData(array $data);

    /**
     * Append a new value onto the form's dataset.
     *
     * @param  string $key The name of the field whose data is contained in $value.
     * @param  mixed  $val The field's value.
     * @return FormInterface Chainable
     */
    public function addFormData($key, $val);

    /**
     * Retrieve the form's dataset.
     *
     * @return array
     */
    public function formData();
}
