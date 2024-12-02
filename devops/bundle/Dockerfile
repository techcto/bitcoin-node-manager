FROM ubuntu:22.04

ARG SHARE
ENV SHARE=${SHARE}
ARG SOLODEV_RELEASE
ENV SOLODEV_RELEASE=${SOLODEV_RELEASE}

RUN apt update
RUN apt-get install -y zip

COPY ./src /node/src
COPY ./public /node/html
COPY ./data /node/data
COPY ./views /node/views
COPY ./composer.json /node/composer.json
COPY ./composer.lock /node/composer.lock

WORKDIR /node

RUN echo $SOLODEV_RELEASE > version.txt && ls -al
RUN zip -r node-manager.zip .
RUN ls -al

#Entrypoint
COPY devops/bundle/bundle.sh /bundle.sh
RUN chmod a+rx /bundle.sh

ENTRYPOINT ["sh", "/bundle.sh"]