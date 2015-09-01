# xServer
WebSockets based notifications stack - pronounced 'zerver'

From the command line: ```php xserver.php``` to start the server listening
**To Control Verbosity of terminal** - use ```php xserver.php``` verbose=true or ```php xserver.php verbose=false``` (default true) when starting the sever
From the client: connect over your chosen WebSockets client! There is an example one included

# Remote Commands
* "list" to view the stack
* "push:xxxxxx" to add to stack
* "pop:xxx" to view message index number xxx
* "memo:xxxxx" to broadcast message to all clients
* "exit" to disconnect for the server
* "elevate:xxxxx" where xxxxxx is the password to elevate client to root and allow server commands below

# Server Commands
* "stop" to pause server from accepting new connections
* "start" to resume server allowing new connections
* "shutdown" to close down the server
* "info" to view the currently connected clients information
* "kill:xxx" to kill a client where "xxx" is the client id