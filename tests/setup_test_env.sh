#!/usr/bin/env bash

rm -rf /tmp/wordpress-tests-lib > dev/null

echo "Setting up the test env for zoninator. Checking dependencies";

which svn > /dev/null

if [ $? -ne 0 ]; then
    echo "cannot find subversion. please install subversion";
    exit 1;
fi

which wget > /dev/null

if [ $? -ne 0 ]; then
    echo "cannot find wget. please install wget";
    exit 1;
fi

which composer > /dev/null

if [ $? -ne 0 ]; then
    echo "cannot find composer. please install composer";
    exit 1;
fi

echo "installing composer dependencies";

composer install > /dev/null

bash bin/install-wp-tests.sh zoninator_test root password localhost latest
