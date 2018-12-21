<?php

namespace Cgit\SuperDraft;

class Action
{
    /**
     * Action GET key
     *
     * @var string
     */
    const ACTION_KEY = 'cgit_superdraft_action';

    /**
     * Post GET key
     *
     * @var string
     */
    const POST_KEY = 'cgit_superdraft_post_id';

    /**
     * Available actions
     *
     * @var array
     */
    private $actions = [
        'create',
        'delete',
        'publish',
    ];

    /**
     * Post/draft combination
     *
     * @var SuperPostDraft
     */
    private $combo;

    /**
     * Construct
     *
     * @param integer $id
     * @return void
     */
    public function __construct($id = null)
    {
        if (!is_null($id)) {
            $this->setPostDraft($id);
        }
    }

    /**
     * Set post/draft combination
     *
     * Instantiate the post/draft object using either the publishd post ID or
     * the draft post ID.
     *
     * @param integer $id
     * @return void
     */
    public function setPostDraft($id)
    {
        $this->combo = new SuperPostDraft($id);
    }

    /**
     * Detect post
     *
     * Instantiate the post/draft object based on GET parameter. Optionally
     * overwrite the current instance if it exists.
     *
     * @param boolean $force
     * @return void
     */
    public function detectPostDraft($force = false)
    {
        $writable = is_null($this->combo) || $force;
        $post = self::postValue();

        if ($writable && $post) {
            $this->setPostDraft($post);
        }
    }

    /**
     * Return action URL
     *
     * @param string $action
     * @param integer $id
     * @return string
     */
    public function url($action, $id = null)
    {
        if (!in_array($action, $this->actions)) {
            return;
        }

        // No ID? Attempt to detect ID in GET.
        if (!$id) {
            $this->detectPostDraft();

            if (!$this->combo) {
                return;
            }

            $id = $this->combo->post()->id();
        }

        // Still no ID? Give up.
        if (!$id) {
            return;
        }

        return add_query_arg([
            self::ACTION_KEY => $action,
            self::POST_KEY => $id,
        ], admin_url());
    }

    /**
     * Perform action
     *
     * @param string $action
     * @return void
     */
    public function perform($action)
    {
        if (!in_array($action, $this->actions) || is_null($this->combo)) {
            return false;
        }

        $this->$action();
    }

    /**
     * Perform action based on GET parameter
     *
     * @return void
     */
    public function detectPerform()
    {
        $action = self::actionValue();
        $this->detectPostDraft();

        if (is_null($this->combo) || !$action) {
            return false;
        }

        $this->perform($action);
    }

    /**
     * Create draft version
     *
     * @return void
     */
    private function create()
    {
        $this->combo->create();
        $this->redirect($this->combo->draft()->edit());
    }

    /**
     * Delete draft version
     *
     * @return void
     */
    private function delete()
    {
        $this->combo->delete();
        $this->redirect($this->combo->post()->edit());
    }

    /**
     * Publish draft version
     *
     * @return void
     */
    private function publish()
    {
        $this->combo->publish();
        $this->redirect($this->combo->post()->edit());
    }

    /**
     * Return GET parameter
     *
     * If the parameter is not set, the second method parameter can be used to
     * set a default value. The default default value is null.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function value($key, $default = null)
    {
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    /**
     * Return action from GET parameter
     *
     * @return mixed
     */
    public static function actionValue()
    {
        return self::value(self::ACTION_KEY);
    }

    /**
     * Return post from GET parameter
     *
     * @return mixed
     */
    public static function postValue()
    {
        return self::value(self::POST_KEY);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @return void
     */
    private function redirect($url)
    {
        if (headers_sent()) {
            return;
        }

        wp_redirect($url);
        exit;
    }
}
