<template>
    <div>
        <table>
            <thead>
                <th v-for="field in fields">{{ field.label }}</th>
            </thead>
            <tbody>
                <tr v-for="row in view">
                    <td v-for="col in row" v-html="col"></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
  // We can assume that the drupalSettings are here.
  let poller;
  let field_options = drupalSettings.vue_table.field_options;
  let field_apis = [];
  Object.keys(field_options).forEach( (value, key) => {
    if (field_options[value].active && field_options[value].url_endpoint.length > 0) {
      field_apis.push({
        name: value,
        url : field_options[value].url_endpoint,
        prop: field_options[value].javascript_property
      });
    }
  });

  /**
   * Recurse downwards for specified property in an Array|Object.
   *
   * @param data Object
   * @param prop The property to search for.
   */
  function findProp (data, prop) {
    return data.map((v) => {
      // if v is an array or something.
      if (typeof v[prop] !== "undefined") {
        return v[prop]
      }
    });
  }

    export default {
      data() {
        return {
            fields: drupalSettings.vue_table.fields,
            view: drupalSettings.vue_table.view,
            update_cycle: ''
        }
      },
      mounted() {
        // Setup the polling
        var pollTiming = drupalSettings.vue_table.update_cycle;
        if (Object.keys(field_apis).length > 0) {
            poller = setInterval(() => { this.poll(); }, pollTiming);
        } else {
          console.warn('No fields associated with an api, no polling started');
        }
      },
      methods: {
        poll() {
          // We have X number of urls to hit. Map over and get an array of promise objects.
          let requests = field_apis.map((v, i) => {
            return Axios.get(field_apis[i].url);
          });
          // When they are all done, we can unpack them.
          Axios.all(requests)
            .then((results) => {
                let data = results.map((v, i) => {

                  if (typeof v.data === "string") {
                    return v.data[field_apis[i].prop];
                  }
                  // Assume we have and object to deal with
                  // use a utility method to recursively find the value we want.
                  return findProp(v.data, field_apis[i].prop);

                });
                // Call update, which will handle updating the model.
                this.update(data);
          });
        },
        update(data) {
          let context = this;
          // For each of the fields with endpoints
          field_apis.forEach((value, index) => {
            let field_name = value.name;
            let field_index = index;
            // Each view > row apply the updates.
            context.view.map((row, key) => {
              row[field_name] = data[field_index][key];
            });
          });
        }
      }
    }
</script>
