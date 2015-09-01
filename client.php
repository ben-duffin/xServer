<!DOCTYPE html>
<html>
<head>
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
  <script>
    var socket, socket_ready, host, port;
    socket_ready = false;

    $(window).bind('unload', function(){
      socket.send(strencode('exit'));
    });

    $(document).ready(function() {
      $('#data').focus();

      if(!("WebSocket" in window)){
        $('.data-box, input, button, #examples').fadeOut("fast");
        $('<p>Oh no, you need a browser that supports WebSockets. How about <a href="http://www.google.com/chrome">Google Chrome</a>?</p>').appendTo('#container');
      }

      function connect(){
        var url = "ws://" + host + ':' + port;

        try{
          socket = new WebSocket(url);

          message('<p class="event">Socket Status: ' + socket.readyState, 'r');

          socket.onopen = function(){
            message('<p class="event">Socket Status: '+socket.readyState+' (open)','r');
          }

          socket.onmessage = function(msg){
            message('<p class="message">Received: '+msg.data, 'r');
          }

          socket.onclose = function(){
            message('<p class="event">Socket Status: '+socket.readyState+' (Closed)');
          }

          socket.onerror = function(event){
            var reason;
            // See http://tools.ietf.org/html/rfc6455#section-7.4.1
            if (event.code == 1000)
              reason = "Normal closure, meaning that the purpose for which the connection was established has been fulfilled.";
            else if(event.code == 1001)
              reason = "An endpoint is \"going away\", such as a server going down or a browser having navigated away from a page.";
            else if(event.code == 1002)
                reason = "An endpoint is terminating the connection due to a protocol error";
              else if(event.code == 1003)
                  reason = "An endpoint is terminating the connection because it has received a type of data it cannot accept (e.g., an endpoint that understands only text data MAY send this if it receives a binary message).";
                else if(event.code == 1004)
                    reason = "Reserved. The specific meaning might be defined in the future.";
                  else if(event.code == 1005)
                      reason = "No status code was actually present.";
                    else if(event.code == 1006)
                        reason = "The connection was closed abnormally, e.g., without sending or receiving a Close control frame";
                      else if(event.code == 1007)
                          reason = "An endpoint is terminating the connection because it has received data within a message that was not consistent with the type of the message (e.g., non-UTF-8 [http://tools.ietf.org/html/rfc3629] data within a text message).";
                        else if(event.code == 1008)
                            reason = "An endpoint is terminating the connection because it has received a message that \"violates its policy\". This reason is given either if there is no other sutible reason, or if there is a need to hide specific details about the policy.";
                          else if(event.code == 1009)
                              reason = "An endpoint is terminating the connection because it has received a message that is too big for it to process.";
                            else if(event.code == 1010) // Note that this status code is not used by the server, because it can fail the WebSocket handshake instead.
                                reason = "An endpoint (client) is terminating the connection because it has expected the server to negotiate one or more extension, but the server didn't return them in the response message of the WebSocket handshake. <br /> Specifically, the extensions that are needed are: " + event.reason;
                              else if(event.code == 1011)
                                  reason = "A server is terminating the connection because it encountered an unexpected condition that prevented it from fulfilling the request.";
                                else if(event.code == 1015)
                                    reason = "The connection was closed due to a failure to perform a TLS handshake (e.g., the server certificate can't be verified).";
                                  else
                                    reason = "Unknown reason";

            message('<p class="error">Socket Error: '+reason+' (Closed)', 'r');
          }
        } catch(exception){
          message('<p>Error'+exception, 'r');
        }
      }//End connect
      function send(){
        var text = $('#data').val();

        if(text==""){
          message('<p class="warning">Please enter a message', 's');
          return ;
        }
        try{
          socket.send(strencode(text));
          message('<p class="event">Sent: '+text, 's')

        } catch(exception){
          message('<p class="warning">','s');
        }
        $('#data').val("").focus();
      }

      function setup(){
        host = $('#host').val();
        port = $('#port').val();
        $('#setup_server').fadeOut('fast', function(){
          $('#websocket').fadeIn('slow');
          connect();
        });
      }

      function message(msg, type){
        switch(type){
          case 's' :
            $('#send_data').append(msg + '</p>');
            $('#send_data').animate({scrollTop: $('#send_data').attr('scrollHeight')});
            break;

          case 'r' :
            $('#receive_data').append(msg + '</p>');
            $('#receive_data').animate({scrollTop: $('#receive_data').attr('scrollHeight')});
            break;
        }
      }

      function strencode( data ) {
        return unescape( encodeURIComponent( JSON.stringify( data ) ) );
      }

      function strdecode( data ) {
        return JSON.parse( decodeURIComponent( escape ( data ) ) );
      }

      $('#setup').bind('click', setup);

      $('#data').keypress(function(event) {
        if (event.keyCode == '13') {
          send();
        }
      });

      $('#disconnect').click(function(){
        socket.close();
      });
    });</script>
  <title>WebSockets Client Example</title>
  <style>
    body {
      font-family:Arial, Helvetica, sans-serif;
    }
    #container {
      border:5px solid grey;
      width:800px;
      margin:0 auto;
      padding:10px;
    }
    #websocket {
      display: none;
    }
    #examples {
      font-size: 11px;
    }
    .data-box {
      padding:5px;
      border:1px solid black;
      height: 400px;
      font-size: 9px;
      width: 380px;
      overflow:auto;
    }
    .data-box p {
      margin:0;
    }
    .event {
      color:#999;
    }
    .warning {
      font-weight:bold;
      color:#CCC;
    }
    .error {
      color: red;
      font-weight:bold;
    }
    input[type="text"] {
      width: 200px;
    }
    #port {
      width: 40px !important;
    }
    table {
      width: 100%;
    }
    table > tr > td {
      width: 50%;
    }
  </style>
</head>
<body>
<div id="wrapper">
  <div id="container">
    <h1>WebSockets Client</h1>
    <div id="setup_server">
      <fieldset>
        <legend>Enter Server Details</legend>
        <input type="text" id="host" placeholder="Enter Host" value="sockets.genasystems.co.uk" />:<input type="text" id="port" placeholder="Enter Port" value="4444" /><button id="setup">Connect to Server</button>
      </fieldset>
    </div>
    <div id="websocket">
      <table border="0">
        <tr>
          <td><h4>Send Data</h4>
            <div id="send_data" class="data-box"></div></td>
          <td><h4>Receive Data</h4>
            <div id="receive_data" class="data-box"></div></td>
        </tr>
      </table>
      <!-- #chatLog -->
      <p id="examples">
        <b>Commands</b>:<br />
        "push:xxxxxx" to add to stack<br />
        "pop:xxx" to view message index number xxx<br />
        "list" to view the stack<br />
        "memo:xxxxx" to broadcast message to all clients<br />
        "exit" to disconnect for the server
      </p>
      <input id="data" type="text"  placeholder="Enter data to send . . . " />
      <button id="disconnect">Disconnect</button>
    </div>
  </div>
  <!-- #container -->

</div>
</body>
</html>
​