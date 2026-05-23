# Testing GitHub actions locally with act

This Dockerfile creates a custom OCI (Docker) image for running GitHub Actions locally using [act](https://github.com/nektos/act). It replicates the GitHub Actions environment to debug CI failures without pushing to GitHub.

## Requirements

- Docker or Podman installed
- install [act](https://github.com/nektos/act)

```bash
wget https://github.com/nektos/act/releases/download/v0.2.84/act_Linux_x86_64.tar.gz
tar -zxvf act_Linux_x86_64.tar.gz
```

## Usage

```bash
# build the image
cd Shaarli
docker build -t shaarli-act .github/act
# run all github actions jobs locally
./act -P ubuntu-latest=shaarli-act --pull=false
# run a specific job
./act -P ubuntu-latest=shaarli-act --pull=false -j php
# debug interactively (optional)
docker run -it --rm -v "$(pwd):/workspace" -w /workspace shaarli-act /bin/bash
# this gives you an interactive shell in the same environment where you can manually run commands from your workflow
```

See https://github.com/shaarli/Shaarli/issues/2136, https://github.com/shaarli/Shaarli/pull/2135 and https://www.kcaran.com/posts/debugging-github-ci-actions.html
