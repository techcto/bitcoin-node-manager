if [ "${SHARE}" ]; then
    ls -al
    echo ${SHARE}
    echo "Sync Share"
    mv node-manager.zip ${SHARE}/node-manager.zip
fi

echo "Finish Bundle"