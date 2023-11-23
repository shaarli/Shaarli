# PubSubHubbub plugin

Enable this plugin to notify a [WebSub](https://en.m.wikipedia.org/wiki/WebSub) (formerly PubSubHubbub) Hub everytime you add or edit a link.
 
This allow hub subcribers to receive update notifications in real time,
which is useful for feed syndication service which supports WebSub/PubSubHubbub.

## Public Hub

By default, Shaarli will use [Google's public hub](http://pubsubhubbub.appspot.com/).

[Here](https://github.com/pubsubhubbub/PubSubHubbub/wiki/Hubs) is a list of public hubs.

You can also host your own WebSub/PubSubHubbub server implementation, such as [phubb](https://github.com/cweiske/phubb).

## cURL

While there is a fallback function to notify the hub, it's recommended that
you have PHP cURL extension enabled to use this plugin.

