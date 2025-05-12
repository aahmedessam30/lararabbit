<?php

namespace AhmedEssam\LaraRabbit\Contracts;

interface SerializerInterface
{
    /**
     * Serialize data to string
     *
     * @param mixed $data
     * @return string
     */
    public function serialize($data): string;

    /**
     * Deserialize string to data
     *
     * @param string $data
     * @return mixed
     */
    public function deserialize(string $data);
}
