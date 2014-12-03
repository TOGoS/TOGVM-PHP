What I want out of this project...

TOGVM
- General-purpose tokenizer and parser
  (should support expression syntax, schema.txt syntax, URIs as expressions)
- Simple, portable core (interp in <1 day)
- JS, PHP interpreters and compile targets
- Strongly typed
- Data structures are all immutable
- Variables are futures, writable at most once
- CPS with forking
- Arbitrarily nested virtual machines
- Built-in...
  - object serialization (format may be overridable)
  - lazy chunked byte sequence type
  - message-based IPC
  - URI resolution, caching
  - Persistent memoization
    - name of function application instance customizable by function metadata (function.instanceNamer, let's say),
      but defaulting to hash of function implementation + arguments (for most strictness)
  - Runtime package resolution with helpful error messages

# Use cases

## Simple programming for game agents

  program(actor) =
    loop(
      waitForStimulation(actor) >>
      checkInventoryFor(actor, someItem) >>= presence ->
      if( presence, moveNorth(actor), moveSouth(actor) )
    )
  
  waitForStimulation(actor), checkInventoryFor(actor, someItem), and moveNorth(actor) return actions
  if( boolean, action ) returns the action if boolean is true, otherwise returns a no-op action
  loop(action) returns an action that does the contained actions in a loop
  a >> b creates an action that does a, then b
  a >>= f creates an action that does a and passes the result to the function f, which returns
    the next action to run 

Layers

- High-level language (see LANGUAGE.txt)
- The common, medium-level representation (see EXPRESSIONS.txt)
- The low-level representation used by an interpreter (though an
  interpreter that works directly off the mid-level format should be
  really easy to build) (see VM.txt)
