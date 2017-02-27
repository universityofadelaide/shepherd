FROM uofa/apache2-php7:feature_scaffold-paths

COPY . /code
RUN mkdir -p /code/web/sites/default/files ; chown -R www-data:www-data /code/web/sites/default/files
