box.cfg{
    listen = 3301,
}

box.schema.user.create('app', {password = 'secret', if_not_exists = true})
box.schema.user.grant('app', 'read,write,execute,create,drop,alter', 'universe', nil, {if_not_exists = true})

require('console').start()
