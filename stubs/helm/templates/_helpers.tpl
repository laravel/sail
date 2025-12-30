{{/*
Expand the name of the chart.
Works with both .Values.name (standard context) and .main.name (_spec template context).
Uses .main.name if in _spec context, otherwise .Values.name, with default "website".
Falls back to standard Helm naming if neither is available.
*/}}
{{- define "sail.name" -}}
{{- $name := "" }}
{{- if .main.name }}
{{- $name = .main.name | default "website" | lower }}
{{- else if .Values.name }}
{{- $name = .Values.name | default "website" | lower }}
{{- else }}
{{- $name = default .Chart.Name .Values.nameOverride }}
{{- end }}
{{- $name | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
Uses .Values.name if provided, otherwise falls back to standard Helm naming.
*/}}
{{- define "sail.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else if .Values.name }}
{{- .Values.name | lower | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Detect the ExternalSecret API version available in the cluster.
Returns "external-secrets.io/v1" if available, otherwise falls back to "external-secrets.io/v1beta1".
*/}}
{{- define "sail.externalSecret.apiVersion" -}}
{{- if .Capabilities.APIVersions.Has "external-secrets.io/v1" -}}
external-secrets.io/v1
{{- else if .Capabilities.APIVersions.Has "external-secrets.io/v1beta1" -}}
external-secrets.io/v1beta1
{{- else -}}
external-secrets.io/v1beta1
{{- end -}}
{{- end }}

