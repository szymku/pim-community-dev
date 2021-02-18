import { flatten } from "./flatten";

describe("Testing flatten function", () => {
  test("it flattens all properties of an object", () => {
    const data = {
        "pim_edition": "Serenity",
        "api_connection": {
          "data_source": {
            "tracked": "2",
            "untracked": 0
          },
          "data_destination": {
            "tracked": "1"
          }
        },
        "php_extensions": [
          "Core",
          "date"
        ]
    };
    expect(flatten(data)).toEqual({
      "pim_edition": "Serenity",
      "api_connection.data_source.tracked": "2",
      "api_connection.data_source.untracked": 0,
      "api_connection.data_destination.tracked": "1",
      "php_extensions": [
        "Core",
        "date"
      ]
    });
  });
});
