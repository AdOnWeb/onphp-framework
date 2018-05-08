<?php
/**
 * Created by IntelliJ IDEA.
 * User: klkvsk
 * Date: 08.05.2018
 * Time: 12:37
 */

class QueryPreparationException extends DatabaseException
{
    /** @var Query */
    protected $query;

    /**
     * @inheritDoc
     */
    public function __construct($message = "", $code = 0, $previous = null, Query $query = null)
    {
        $this->query = $query;

        if (__LOCAL_DEBUG__) {
            $message .= "\r\n\r\nQuery dump: " . print_r($query, true);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }



}