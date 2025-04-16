# Use official PHP image with Apache
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    perl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    build-essential \
    libssl-dev \
    cpanminus \
    wget \
    cron \
    tar \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Perl modules
RUN cpanm Email::Simple Email::Sender::Simple Email::Sender::Transport::SMTP

RUN cpanm LWP::Simple Mozilla::CA

RUN cpanm IO::Socket::SSL

RUN cpanm LWP::Protocol::https

# Set BLAST version (update this to the latest version as needed)
ENV BLAST_VERSION=2.16.0

# Download and install BLAST+
RUN wget https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/${BLAST_VERSION}/ncbi-blast-${BLAST_VERSION}+-x64-linux.tar.gz \
    && tar -xzf ncbi-blast-${BLAST_VERSION}+-x64-linux.tar.gz \
    && rm ncbi-blast-${BLAST_VERSION}+-x64-linux.tar.gz \
    && mv ncbi-blast-${BLAST_VERSION}+ /usr/local/blast

# Add BLAST binaries to PATH
ENV PATH="/usr/local/blast/bin:${PATH}"

# Set VIROBLAST_DB_PATH environment variable
ENV VIROBLAST_DB_PATH="/viroblast_data"

# Copy application files
COPY ./src /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN chmod -R 755 /var/www/html/outputs
RUN chmod -R 755 /var/www/html/stats

# Install Composer (PHP package manager)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# copy crontab file into the container, replacing the placeholder with the actual path
RUN cat crontab | sed "s/__REPLACE_ME__/${VIROBLAST_DB_PATH}/" > /etc/cron.d/crontab

# set permissions for the crontab file
RUN chmod 0644 /etc/cron.d/crontab

# Apply cron job
RUN crontab /etc/cron.d/crontab

# create the directory to store log file to run the cron jobs
RUN mkdir /var/log/cron

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD service cron start && apache2-foreground