(function(React, ReactDOM, $) {

  var SiteEnvironmentStatus = React.createClass({
    displayName: 'SiteEnvStatus',
    intervalID: '',
    getInitialState: function() {
      return {
        instanceState: this.props.state
      }
    },
    setIntervalFromServer: function () {
      this.intervalID = setInterval(this.loadStateFromServer, 5000);
    },
    componentDidMount: function() {
      this.loadStateFromServer();
      // Component has been mounted, now we can update the state every 5 seconds.
      this.setIntervalFromServer(this.loadStateFromServer, 5000);
    },
    componentWillUpdate: function(nextProps, nextState) {
      if (nextState.instanceState.failed == 0 && nextState.instanceState.building == 0 ) {
        // We reached a level of desired state.
        // Everything on this environment is running.
        // Set the inverval time to something longer
        // Every 10 minutes.
        clearInterval(this.intervalID);
        this.setIntervalFromServer(this.loadStateFromServer, 100000);
      }
    },
    loadStateFromServer: function() {
      // Make an ajax request to the server.
      $.ajax({
        url: this.props.url,
        success: function(data) {
         // Got the data, set the state.
         var newState = {
            running: 0,
            failed: 0,
            building: 0
          };
          data.forEach(function(instance) {
            // Each instance push state.
            var state = instance.field_shp_state[0].value;
            if (state == "starting") {
              newState['building']++;
            } else if (state == "stopped" || state == "stopping") {
              newState['failed']++;
            } else {
              newState[state]++;
            }
          });
          // Update.
          this.setState({instanceState: newState})
        }.bind(this)
      });
    },
    render: function() {
      var spans = [];
      var classes = {
        running : 'u-text--success',
        building: 'u-text--warning',
        failed: 'u-text--error'
      };
      $.each(this.state.instanceState, function(index, val) {
        if (val > 0) {
          var capitalise = index.charAt(0).toUpperCase() + index.slice(1);
          spans.push( React.createElement('span', {className: classes[index]}, val + " " + capitalise + " ") );
        }
      });

      return(  React.createElement("div", {}, spans) );
    }
  });

  var mountNodes = $(".sm_site_environment_status");

  mountNodes.each(function(index, element) {
    var data = $(element).data();
    ReactDOM.render(
      React.createElement(SiteEnvironmentStatus, {
        url: data.url,
        state: {
          running: data.running,
          failed: data.failed,
          building: data.building
        }
      }),
      element
    );
  });

})(React, ReactDOM, jQuery);
