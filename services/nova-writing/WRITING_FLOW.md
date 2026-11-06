# Writing flow acceptance — November 6, 2026

The isolated browser journey exercised a normal player’s desk, blank scene, rich text, autosave, keyboard @ picker, collaborator access confirmation, targeted email, publication cancellation/confirmation, and the published Exchange deep link as another player. One targeted email and six individual publication notices were captured by fake local SMTP. No private prose appeared in those messages; no real players received test notices.

The review found two connected defects: native publication prompts stalled browser interaction, and Milkdown’s exact `<br />` empty-paragraph markers appeared literally in the reader and prevented rich-mode reopening. Publication now uses an in-page dialog describing audience and notifications. Both Markdown renderers recognize only attribute-free break nodes; other raw HTML remains escaped, including break tags with attributes. Code examples remain literal. The source bytes are not rewritten. Editor guards allow this exact safe marker, and word counts exclude it.

A second private fixture with a blank paragraph and bold text was saved and reopened in rich mode through the browser. Server formatting safety, client rendering, autosave/recovery, writer-picker and notebook tests passed. Test authentication in the isolated Exchange is a fake adapter; production sign-in was not modified.

For subsequent releases, repeat this journey with local fake accounts and captured mail, then verify the deployed confirmation through the live blank editor without posting a test story to real players. Do not substitute successful source checks for browser actions.
