<?php

function test_route_invalid_register_routes(): array
{
    return [
        [
            'method' => 'I_INVENT_MY_HTTP_METHODS',
            'route' => '/hello',
            'callable' => 'getFunction',
        ],
    ];
}
