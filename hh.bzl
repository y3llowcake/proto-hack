cmd_template = """\
#!/bin/bash
set -euo pipefail
# ls -R
/usr/bin/hhvm \
    -d hhvm.repo.central.path=/tmp/proto-gen-hack.repo\
    {args} \
    "$@"
"""

def _hh_test(ctx):
    out = ctx.outputs.out
    script_content = cmd_template.format(
        args = ctx.attr.hh_args,
    )
    ctx.actions.write(out, script_content, is_executable = True)
    runfiles = ctx.runfiles(files = ctx.files.srcs)
    return [DefaultInfo(executable = out, runfiles = runfiles)]

hh_test = rule(
    _hh_test,
    attrs = {
        "srcs": attr.label_list(
            allow_files = True,
            mandatory = True,
        ),
        "hh_args": attr.string(mandatory = True),
    },
    outputs = {
        "out": "%{name}.bin",
    },
    test = True,
)

# TODO this is broken because hh_client isn't following symlinks?
def _hh_client_test(ctx):
    out = ctx.outputs.out
    script_content = "#!/bin/bash\nset -euo pipefail\nhh_client"
    ctx.actions.write(out, script_content, is_executable = True)
    runfiles = ctx.runfiles(files = ctx.files.srcs)
    return [DefaultInfo(executable = out, runfiles = runfiles)]

hh_client_test = rule(
    _hh_client_test,
    attrs = {
        "srcs": attr.label_list(
            allow_files = True,
            mandatory = True,
        ),
    },
    outputs = {
        "out": "%{name}.bin",
    },
    test = True,
)
