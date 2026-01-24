# Minimal TCPDF-compatible shim

This repository is network-restricted in the execution environment, so the
full TCPDF distribution could not be fetched. A small compatibility shim is
provided to support the label PDF output pathway used by `AEGIS_Codes`.

The shim implements only the subset of APIs required by the plugin's
`handle_print()` method.
