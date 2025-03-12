FROM ubuntu-latest

# Install necessary dependencies
RUN apt-get update && apt-get install -y \
    sudo \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Get correct version of nodejs
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash - \
    && sudo apt install -y nodejs

# Create a non-root user (same as GitHub Actions)
RUN useradd -m runner && \
    echo "runner ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Set user to 'runner'
USER runner

# Set working directory (GitHub Actions uses this location)
WORKDIR /home/runner

CMD ["/bin/bash"]
