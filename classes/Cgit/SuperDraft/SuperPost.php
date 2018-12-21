<?php

namespace Cgit\SuperDraft;

class SuperPost
{
    /**
     * WP_Post instance
     *
     * @var WP_Post
     */
    private $post;

    /**
     * Construct
     *
     * @param mixed $post
     * @return void
     */
    public function __construct($post = null)
    {
        $this->setPostInstance($post);
    }

    /**
     * Set post based on ID or WP_Post instance
     *
     * This method accepts a WP_Post instance, an instance of this class, or a
     * numeric variable representing the post ID. If the post exists, the method
     * returns true. If not, the method returns false.
     *
     * @param mixed $post
     * @return boolean
     */
    private function setPostInstance($post)
    {
        $class = 'WP_Post';

        // Post has not been specified
        if (is_null($post)) {
            return false;
        }

        // Post is an instance of WP_Post: we will assume that it is a valid
        // post and return true.
        if (is_a($post, $class)) {
            $this->post = $post;

            return true;
        }

        // Post is an instance of this class. If it represents a valid post,
        // return true; otherwise, return false.
        if (is_a($post, get_class($this))) {
            $this->post = get_post($post->id());

            return (bool) $this->id();
        }

        // Post might be a valid ID: attempt to create WP_Post instance and
        // return a boolean based on whether the post exists or not.
        if (is_numeric($post)) {
            $this->post = get_post($post);

            return is_a($post, $class);
        }

        return false;
    }

    /**
     * Return post ID
     *
     * @return integer
     */
    public function id()
    {
        if (is_object($this->post) && property_exists($this->post, 'ID')) {
            return (int) $this->post->ID;
        }

        return 0;
    }

    /**
     * Return post title
     *
     * @return string
     */
    public function title()
    {
        return get_the_title($this->post);
    }

    /**
     * Return post URL
     *
     * @return string
     */
    public function url()
    {
        return get_permalink($this->post);
    }

    /**
     * Return post edit URL
     *
     * @return string
     */
    public function edit()
    {
        return get_edit_post_link($this->post);
    }

    /**
     * Return post meta
     *
     * If a meta key is provided, a single value will be returned. If no meta
     * key is provided, all post meta will be returned.
     *
     * @param mixed $key
     * @return mixed
     */
    public function meta($key = null)
    {
        if (is_null($key)) {
            return get_post_meta($this->id());
        }

        return get_post_meta($this->id(), $key, true);
    }

    /**
     * Export post data
     *
     * Return all post data as an associative array. By default, the data does
     * not include the post ID or date, which makes it safe to merge into
     * another post.
     *
     * @param boolean $unsafe
     * @return array
     */
    public function export($unsafe = false)
    {
        $data = (array) $this->post;

        if ($unsafe) {
            return $data;
        }

        return array_diff_key($data, array_flip([
            'ID',
            'post_date',
            'post_date_gmt',
            'post_status',
            'post_name',
            'guid',
        ]));
    }

    /**
     * Import content from another post
     *
     * @param mixed $source
     * @return boolean
     */
    public function import($source)
    {
        $source = new self($source);

        // No source? No destination? No import.
        if (!$this->id() || !$source->id()) {
            return false;
        }

        // Import post data
        $data = $source->export();
        $data['ID'] = $this->id();

        wp_update_post($data);

        // Import meta and terms
        $this->importMeta($source);
        $this->importTerms($source);

        return true;
    }

    /**
     * Import meta from another post
     *
     * @param SuperPost $source
     * @return void
     */
    private function importMeta($source)
    {
        // Posts with drafts and the drafts themselves have meta data to
        // identify their published or draft versions. These should not be
        // imported when merging one into the other.
        $exclude = [
            Plugin::POST_META_KEY,
            Plugin::DRAFT_META_KEY,
        ];

        $post1 = $source->id();
        $post2 = $this->id();

        $meta1 = array_diff_key(get_post_meta($post1), $exclude);
        $meta2 = array_diff_key(get_post_meta($post2), $meta1, $exclude);

        // Copy meta from source to destination
        foreach ($meta1 as $key => $values) {
            foreach ($values as $value) {
                update_post_meta($post2, $key, $value);
            }
        }

        // Delete data from destination when keys no longer exist in source
        foreach ($meta2 as $key => $values) {
            delete_post_meta($post2, $key);
        }
    }

    /**
     * Import terms from another post
     *
     * @param SuperPost $source
     * @return void
     */
    private function importTerms($source)
    {
        $post1 = $source->id();
        $post2 = $this->id();
        $taxons = get_object_taxonomies(get_post_type($post1));

        foreach ($taxons as $taxon) {
            $terms = wp_get_object_terms($post1, $taxon, [
                'fields' => 'slugs',
            ]);

            wp_set_object_terms($post2, $terms, $taxon, false);
        }
    }
}
