# API tests for Shaarli

## API? What API?

Indeed, there's no [formal API](https://github.com/shaarli/Shaarli/issues/16) in
the sense of a designed, coherent, complete interface.

However, there's functionality reachable via HTTP. I call those calls the API –
the GET & POST request parameters and the response HTTP code and body. When it
comes to response bodies, try to remain as theme independant as possible.

Also the test coverage is quite sparse and covers only the few use cases I
needed for http://app.mro.name/ShaarliOS, but may well grow over time if helpful.

## Design Goals

The tests cover public facing behaviour, so during the tests only HTTP is used,
no direct file access or other internal knowledge a.k.a. private implementation
details.

It's mostly basic bash scripts based on `curl` and `xmllint`. They should easily
run in isolation as well as part of the test suite.

I [avoided esoteric or hip
frameworks](https://github.com/mro/Shaarli-API-test/issues/2) to keep the
dependencies basic and learning curve (for myself) low.

| Quality         | very good | good | normal | irrelevant |
|-----------------|:---------:|:----:|:------:|:----------:|
| Functionality   |           |      |    ×   |            |
| Reliability     |           |  ×   |        |            |
| Usability       |           |      |    ×   |            |
| Efficiency      |           |      |    ×   |            |
| Changeability   |           |  ×   |        |            |
| Portability     |           |  ×   |        |            |

## Usage

Run the complete test suite (incl. launching a webserver - php):

    $ bash tests/api/run.sh

Run a single test (against an already running webserver):

    $ bash tests/api/test-post.sh
