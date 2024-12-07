# Update App URL
echo "APP_URL: "${APP_URL}
sed -i -e "s/{{APP_URL}}/${APP_URL}/g" /usr/local/apache2/conf/extra/node.conf

#Log to error log for production.  Restart monitors log for vhost changes.
if [ "$APP_ENV" != "dev" ]; then
    echo "" >> /usr/local/apache2/conf/extra/node.conf
    echo "ErrorLog /var/log/apache2/error.log" >> /usr/local/apache2/conf/extra/node.conf
fi

COMMAND=${COMMAND:="httpd-foreground"}

${COMMAND}